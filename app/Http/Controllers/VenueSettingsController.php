<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\Pricing;
use App\Services\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VenueSettingsController extends Controller
{
    public function index(Request $request)
    {
        $venue = \App\Services\VenueContext::venue();
        $courts = $venue->courts()->orderBy('id')->get();
        $hours = $venue->hours()->get()->keyBy('weekday');
        $staff = $venue->members()->wherePivotIn('role', ['staff','manager'])->orderBy('name')->get();

        return view('staff.settings', compact('venue', 'courts', 'hours', 'staff'));
    }

    public function venue(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.Schedule::PHONE],
            'timezone' => ['required', 'timezone:all'],
        ]);
        DB::transaction(function () use ($request, $data) {
            $venue = Venue::query()->lockForUpdate()->findOrFail(\App\Services\VenueContext::venue()->id);
            if ($venue->timezone !== $data['timezone'] && $venue->openPlaySessions()
                ->whereNull('cancelled_at')->where('ends_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['timezone' => 'Cancel future Open Play sessions before changing the time zone.']);
            }
            $venue->update($data);
        }, 3);

        return $this->saved('Venue details saved.');
    }

    public function hours(Request $request)
    {
        $rules = ['hours' => ['required', 'array:0,1,2,3,4,5,6', 'size:7']];
        foreach (range(0, 6) as $weekday) {
            $rules["hours.$weekday"] = ['required', 'array:opens_at,closes_at,closed'];
            $rules["hours.$weekday.opens_at"] = ['required', 'regex:'.Schedule::START_TIME];
            $rules["hours.$weekday.closes_at"] = ['required', 'regex:'.Schedule::END_TIME];
            $rules["hours.$weekday.closed"] = ['sometimes', 'boolean'];
        }
        $data = $request->validate($rules);
        foreach ($data['hours'] as $weekday => $hours) {
            if (empty($hours['closed']) && $hours['opens_at'] >= $hours['closes_at']) {
                throw ValidationException::withMessages(["hours.$weekday.closes_at" => 'On open days the closing time must follow the opening time.']);
            }
        }
        DB::transaction(function () use ($request, $data) {
            $venue = Venue::query()->lockForUpdate()->findOrFail(\App\Services\VenueContext::venue()->id);
            foreach ($venue->openPlaySessions()->whereNull('cancelled_at')->where('ends_at', '>', now())->get() as $session) {
                $start = $session->starts_at->setTimezone($venue->timezone);
                $end = $session->ends_at->setTimezone($venue->timezone);
                $hours = $data['hours'][$start->dayOfWeek];
                $endTime = $end->toDateString() !== $start->toDateString() ? '24:00' : $end->format('H:i');
                if (!empty($hours['closed']) || $start->format('H:i') < $hours['opens_at'] || $endTime > $hours['closes_at']) {
                    throw ValidationException::withMessages(['hours' => 'Cancel or reschedule conflicting Open Play sessions before changing opening hours.']);
                }
            }
            foreach ($data['hours'] as $weekday => $hours) {
                $venue->hours()->updateOrCreate(['weekday' => $weekday], $hours + ['closed' => false]);
            }
        }, 3);

        return $this->saved('Opening hours saved.');
    }

    public function addCourt(Request $request)
    {
        $venue = \App\Services\VenueContext::venue();
        $data = $this->courtData($request, $venue);
        $venue->courts()->create($data);

        return $this->saved('Court added.');
    }

    public function updateCourt(Request $request, int $court)
    {
        DB::transaction(function () use ($request, $court) {
            $venue = Venue::query()->lockForUpdate()->findOrFail(\App\Services\VenueContext::venue()->id);
            $record = $venue->courts()->lockForUpdate()->findOrFail($court);
            $data = $this->courtData($request, $venue, $court);
            if (!$data['active'] && $record->blocks()->whereNotNull('open_play_session_id')->where('ends_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['active' => 'Cancel upcoming Open Play sessions before deactivating this court.']);
            }
            $record->update($data);
        }, 3);

        return $this->saved('Court details saved.');
    }

    public function addStaff(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:72'],
        ]);
        DB::transaction(fn () => \App\Services\VenueContext::venue()->users()->create($data + ['role' => 'staff']));

        return $this->saved('Staff account added.');
    }

    public function removeStaff(Request $request, int $staff)
    {
        $venue = \App\Services\VenueContext::venue();
        $venue->members()->wherePivotIn('role',['staff','manager'])->findOrFail($staff);
        $venue->members()->wherePivotIn('role',['staff','manager'])->detach($staff);

        return $this->saved('Staff access removed.');
    }

    private function courtData(Request $request, Venue $venue, ?int $court = null): array
    {
        $unique = Rule::unique('courts', 'name')->where('venue_id', $venue->id);
        if ($court !== null) {
            $unique->ignore($court);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', $unique],
            'rate' => ['required', 'regex:'.Pricing::PESOS],
            'surface_type' => ['nullable', 'in:indoor,outdoor'],
            'active' => ['required', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'hourly_rate' => Pricing::centavos($data['rate']),
            'surface_type' => $data['surface_type'] ?? null,
            'active' => $data['active'],
        ];
    }

    private function saved(string $status)
    {
        return \App\Services\VenueContext::redirect('settings')->with('status', $status);
    }
}
