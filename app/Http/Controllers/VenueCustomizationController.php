<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Venue;
use App\Services\EmvPaymentQr;
use App\Services\Pricing;
use App\Services\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class VenueCustomizationController extends Controller
{
    public function index(Request $request)
    {
        $venue = $request->user()->venue;
        $media = $venue->media()->get(['kind', 'version'])->keyBy('kind');
        $closures = $venue->closures()->where('date', '>=', CarbonImmutable::today($venue->timezone)->toDateString())
            ->orderBy('date')->get();
        $rates = $venue->rates()->with('court')->orderBy('starts_at')->get();
        $courts = $venue->courts()->orderBy('name')->get();
        $editing = null;
        if ($request->filled('rate')) {
            $id = $request->validate(['rate' => ['integer']])['rate'];
            $editing = $venue->rates()->findOrFail($id);
        }

        return view('staff.customization', compact('venue', 'media', 'closures', 'rates', 'courts', 'editing'));
    }

    public function branding(Request $request)
    {
        $data = $request->validate([
            'brand_color' => ['nullable', 'regex:/^#[a-fA-F0-9]{6}$/D'],
            'map_url' => ['nullable', 'url:https', 'max:2048'],
            'messenger_url' => ['nullable', 'url:https', 'max:2048'],
            'logo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:max_width=4096,max_height=4096', 'max:1024'],
            'cover' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:max_width=4096,max_height=4096', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_cover' => ['sometimes', 'boolean'],
        ]);

        $this->withVenueLock($request, function (Venue $venue) use ($data) {
            $venue->update(collect($data)->only(['brand_color', 'map_url', 'messenger_url'])->all());
            foreach (['logo', 'cover'] as $kind) {
                $file = $data[$kind] ?? null;
                if ($file) {
                    $content = $file->get();
                    $venue->media()->updateOrCreate(['kind' => $kind], [
                        'mime' => $file->getMimeType(),
                        'version' => hash('sha256', $content),
                        'content' => base64_encode($content),
                    ]);
                } elseif ($data['remove_'.$kind] ?? false) {
                    $venue->media()->where('kind', $kind)->delete();
                }
            }
        });

        return $this->saved('Booking page branding saved.', 'branding');
    }

    public function payment(Request $request, EmvPaymentQr $generator)
    {
        $enabled = $request->boolean('qr_payments_enabled');
        $isRequired = Rule::requiredIf($enabled);
        $data = $request->validate([
            'qr_payments_enabled' => ['sometimes', 'boolean'],
            'payment_qr_base' => [$isRequired, 'nullable', 'string', 'max:512'],
            'payment_messenger_url' => [$isRequired, 'nullable', 'regex:~^https://(?:m\.me/|(?:www\.)?facebook\.com/messages/t/)[a-zA-Z0-9._-]+/?$~D', 'max:255'],
            'provider_confirmed' => $enabled ? ['required', 'accepted'] : ['sometimes', 'accepted'],
        ], [
            'payment_messenger_url.regex' => 'Enter an HTTPS m.me or Facebook /messages/t/ link.',
        ]);

        if ($data['payment_qr_base'] ?? null) {
            try {
                $generator->generate($data['payment_qr_base'], 100);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['payment_qr_base' => $exception->getMessage()]);
            }
        }

        $this->withVenueLock($request, fn (Venue $venue) => $venue->update([
            'qr_payments_enabled' => $enabled,
            'payment_qr_base' => $data['payment_qr_base'] ?? null,
            'payment_messenger_url' => $data['payment_messenger_url'] ?? null,
        ]));

        return $this->saved('Payment settings saved.', 'payments');
    }

    public function rules(Request $request)
    {
        $data = $request->validate([
            'booking_durations' => ['required', 'array', 'min:1', 'max:4'],
            'booking_durations.*' => ['integer', 'distinct', Rule::in(Venue::BOOKING_DURATIONS)],
            'advance_days' => ['required', 'integer', 'between:0,180'],
            'minimum_notice' => ['required', 'integer', 'between:0,10080'],
            'cancellation_policy' => ['nullable', 'string', 'max:1500'],
        ]);
        $data['booking_durations'] = array_map('intval', array_values($data['booking_durations']));
        sort($data['booking_durations']);
        $this->withVenueLock($request, fn (Venue $venue) => $venue->update($data));

        return $this->saved('Booking rules saved.', 'booking-rules');
    }

    public function closeDate(Request $request, Schedule $schedule)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:120'],
        ]);

        $this->withVenueLock($request, function (Venue $venue) use ($data, $schedule) {
            if ($data['date'] < CarbonImmutable::today($venue->timezone)->toDateString()) {
                throw ValidationException::withMessages(['date' => 'Choose today or a future date.']);
            }
            if ($venue->closures()->where('date', $data['date'])->exists()) {
                throw ValidationException::withMessages(['date' => 'That date already has a closure.']);
            }

            $start = $schedule->time($venue, $data['date'], '00:00');
            $end = $schedule->time($venue, $data['date'], '24:00');
            $hasBookings = Booking::query()->whereIn('court_id', $venue->courts()->select('id'))
                ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                ->occupying()->where('ends_at', '>', now())->exists();
            if ($hasBookings) {
                throw ValidationException::withMessages(['date' => 'Cancel or move the remaining reservations before closing this date.']);
            }

            $venue->closures()->create($data);
        });

        return $this->saved('Venue closure added.', 'closures');
    }

    public function removeClosure(Request $request, int $closure)
    {
        $this->withVenueLock($request, fn (Venue $venue) => $venue->closures()->findOrFail($closure)->delete());

        return $this->saved('Venue closure removed.', 'closures');
    }

    public function saveRate(Request $request, ?int $rate = null)
    {
        $venue = $request->user()->venue;
        $data = $request->validate([
            'court_id' => ['nullable', 'integer', Rule::exists('courts', 'id')->where('venue_id', $venue->id)],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'distinct', 'between:0,6'],
            'starts_at' => ['required', 'regex:'.Schedule::START_TIME],
            'ends_at' => ['required', 'regex:'.Schedule::END_TIME],
            'rate' => ['required', 'regex:'.Pricing::PESOS],
        ]);
        if ($data['starts_at'] >= $data['ends_at']) {
            throw ValidationException::withMessages(['ends_at' => 'The end time must be after the start time.']);
        }

        $weekdays = array_map('intval', array_values($data['weekdays']));
        $this->withVenueLock($request, function (Venue $venue) use ($data, $weekdays, $rate) {
            $existing = $rate === null ? null : $venue->rates()->findOrFail($rate);
            $court = $data['court_id'] ?? null;
            $conflict = $venue->rates()->where('court_id', $court)
                ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
                ->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])
                ->get()->contains(fn ($rule) => (bool) array_intersect($rule->weekdays, $weekdays));
            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => 'This rate overlaps another rate for the same court scope and weekday.']);
            }

            $attributes = [
                'court_id' => $court,
                'weekdays' => $weekdays,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'hourly_rate' => Pricing::centavos($data['rate']),
            ];
            if ($existing) {
                $existing->update($attributes);
            } else {
                $venue->rates()->create($attributes);
            }
        });

        return $this->saved('Court rate saved.', 'rates');
    }

    public function removeRate(Request $request, int $rate)
    {
        $this->withVenueLock($request, fn (Venue $venue) => $venue->rates()->findOrFail($rate)->delete());

        return $this->saved('Court rate removed.', 'rates');
    }

    private function withVenueLock(Request $request, callable $action): void
    {
        DB::transaction(function () use ($request, $action) {
            $venue = Venue::query()->lockForUpdate()->findOrFail($request->user()->venue_id);
            $action($venue);
        }, 3);
    }

    private function saved(string $message, string $section)
    {
        return redirect()->to(route('settings.customization').'#'.$section)->with('status', $message);
    }
}
