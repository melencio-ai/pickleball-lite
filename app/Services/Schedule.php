<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CourtBlock;
use App\Models\Venue;
use App\Models\VenueHour;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class Schedule
{
    public const START_TIME = '/^(?:[01][0-9]|2[0-3]):(?:00|30)$/D';

    public const END_TIME = '/^(?:(?:[01][0-9]|2[0-3]):(?:00|30)|24:00)$/D';

    public const PHONE = '/^\+?[0-9][0-9() .-]{5,29}[0-9]$/D';

    public function __construct(private Pricing $pricing, private EmvPaymentQr $paymentQr) {}

    public function hours(Venue $venue, string $date): ?VenueHour
    {
        $weekday = CarbonImmutable::parse($date, $venue->timezone)->dayOfWeek;

        if ($venue->closures()->where('date', $date)->exists()) {
            return null;
        }

        return $venue->hours()->where('weekday', $weekday)->first();
    }

    public function time(Venue $venue, string $date, string $time): CarbonImmutable
    {
        $local = CarbonImmutable::parse($date, $venue->timezone)->startOfDay();
        if ($time === '24:00') {
            return $local->addDay()->utc();
        }

        return CarbonImmutable::parse("$date $time", $venue->timezone)->utc();
    }

    public function overlaps(Relation $relation, CarbonImmutable $start, CarbonImmutable $end): Relation
    {
        return $relation->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    public function availability(Venue $venue, string $date, int $duration, bool $public = true): array
    {
        $hours = $this->hours($venue, $date);
        if (! $hours || $hours->closed) {
            return [];
        }

        $open = $this->time($venue, $date, $hours->opens_at);
        $close = $this->time($venue, $date, $hours->closes_at);
        $earliest = CarbonImmutable::now('UTC')->addMinutes($public ? $venue->minimum_notice : 0);
        $rates = $this->pricing->dailyRates($venue, $date);

        return $venue->courts()->where('active', true)->orderBy('id')->get()
            ->map(function ($court) use ($open, $close, $earliest, $duration, $venue, $date, $rates) {
                $busy = $this->overlaps($court->bookings(), $open, $close)
                    ->occupying()->get()
                    ->concat($this->overlaps($court->blocks(), $open, $close)->get());

                $slots = [];
                for ($start = $open; ($end = $start->addMinutes($duration))->lte($close); $start = $start->addMinutes(30)) {
                    $local = $start->setTimezone($venue->timezone);
                    if ($start->lte($earliest) || $local->format('Y-m-d') !== $date
                        || ! $start->equalTo($this->time($venue, $date, $local->format('H:i')))
                        || $busy->contains(
                            fn ($entry) => $entry->starts_at->lt($end) && $entry->ends_at->gt($start)
                        )) {
                        continue;
                    }

                    $total = $this->pricing->quote($court, $start, $end, $venue->timezone, $rates);
                    if ($venue->qr_payments_enabled && $total === 0) {
                        continue;
                    }
                    $slots[$local->format('H:i')] = [
                        'label' => $local->format('g:i A'),
                        'total' => $total,
                    ];
                }

                return ['court' => $court, 'slots' => $slots];
            })->all();
    }

    public function reserve(Venue $venue, array $data, string $source): Booking
    {
        return $this->occupy($venue, $data, $source);
    }

    public function block(Venue $venue, array $data): CourtBlock
    {
        return $this->occupy($venue, $data, null);
    }

    private function occupy(Venue $venue, array $data, ?string $source): Booking|CourtBlock
    {
        return DB::transaction(function () use ($venue, $data, $source) {
            // Settings take an exclusive venue lock. Reservations can share it
            // and serialize only with other writes on the same court.
            $venue = Venue::query()->sharedLock()->findOrFail($venue->id);
            if ($source === 'online' && !$venue->acceptsPublicBookings()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['date'=>'This venue is not accepting online reservations.']);
            }
            $legal = $source === 'online' ? LegalPolicies::context($venue,lock:true) : null;
            if ($legal) LegalPolicies::validate($data,$legal);
            $court = $venue->courts()->lockForUpdate()->findOrFail($data['court_id']);
            $start = $this->time($venue, $data['date'], $data['start']);
            $end = $source
                ? $start->addMinutes((int) $data['duration'])
                : $this->time($venue, $data['date'], $data['end']);

            $local = $start->setTimezone($venue->timezone);
            if (! $court->active || $start->lte(CarbonImmutable::now('UTC')) || $end->lte($start)
                || $local->format('Y-m-d H:i') !== $data['date'].' '.$data['start']) {
                throw ValidationException::withMessages(['start' => 'Choose an active court and a valid future time.']);
            }

            if ($source) {
                if (! in_array((int) $data['duration'], $venue->durations(), true)) {
                    throw ValidationException::withMessages(['duration' => 'That booking duration is no longer available.']);
                }
                if ($source === 'online' && (
                    $start->lte(CarbonImmutable::now('UTC')->addMinutes($venue->minimum_notice))
                    || $data['date'] > CarbonImmutable::today($venue->timezone)->addDays($venue->advance_days)->toDateString()
                )) {
                    throw ValidationException::withMessages(['start' => 'That time falls outside the venue’s booking window.']);
                }

                $hours = $this->hours($venue, $data['date']);
                if (! $hours || $hours->closed || $start->lt($this->time($venue, $data['date'], $hours->opens_at))
                    || $end->gt($this->time($venue, $data['date'], $hours->closes_at))) {
                    throw ValidationException::withMessages(['start' => 'This booking falls outside the venue’s opening hours.']);
                }
            }

            $bookingExists = $this->overlaps($court->bookings(), $start, $end)->occupying()->exists();
            $blockExists = $this->overlaps($court->blocks(), $start, $end)->exists();
            if ($bookingExists || $blockExists) {
                throw ValidationException::withMessages(['start' => 'That time is no longer available. Choose another slot.']);
            }

            if (! $source) {
                return $court->blocks()->create(['starts_at' => $start, 'ends_at' => $end, 'reason' => $data['reason']]);
            }

            $total = $this->pricing->quote($court, $start, $end, $venue->timezone, $this->pricing->dailyRates($venue, $data['date']));
            if ($source === 'online' && (int) $data['quoted_total'] !== $total) {
                throw ValidationException::withMessages(['start' => 'The rate has changed. Review the new price before booking.']);
            }

            $payment = [];
            if ($venue->qr_payments_enabled) {
                if (! $venue->payment_qr_base || ! $venue->payment_messenger_url) {
                    throw ValidationException::withMessages(['start' => 'This venue’s QR payment setup is incomplete.']);
                }
                try {
                    $payload = $this->paymentQr->generate($venue->payment_qr_base, $total);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['start' => $exception->getMessage()]);
                }

                $payment = [
                    'payment_token' => Str::random(48),
                    'payment_qr_payload' => $payload,
                    'payment_messenger_url' => $venue->payment_messenger_url,
                    'payment_expires_at' => min(now()->toImmutable()->addMinutes(20), $start),
                ];
            }

            $booking = $court->bookings()->create([
                'reference' => (string) Str::ulid(),
                'starts_at' => $start,
                'ends_at' => $end,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'total' => $total,
                'source' => $source,
                'cancellation_policy' => LegalPolicies::venuePolicy($venue),
            ] + $payment);
            if ($legal) LegalPolicies::record($legal,'booking',$booking->id,$venue->id);
            return $booking;
        }, 3);
    }
}
