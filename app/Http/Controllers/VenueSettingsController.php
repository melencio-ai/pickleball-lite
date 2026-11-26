<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VenueSettingsController extends Controller
{
    public function index(Request $request)
    {
        $venue = $request->user()->venue;
        $courts = $venue->courts()->orderBy('id')->get();
        $hours = $venue->hours()->get()->keyBy('weekday');
        $staff = $venue->users()->where('role', 'staff')->orderBy('name')->get();

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
        $request->user()->venue()->update($data);

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
            $venue = Venue::query()->lockForUpdate()->findOrFail($request->user()->venue_id);
            foreach ($data['hours'] as $weekday => $hours) {
                $venue->hours()->updateOrCreate(['weekday' => $weekday], $hours + ['closed' => false]);
            }
        }, 3);

        return $this->saved('Opening hours saved.');
    }

    public function addCourt(Request $request)
    {
        $venue = $request->user()->venue;
        $data = $this->courtData($request, $venue);
        $venue->courts()->create($data);

        return $this->saved('Court added.');
    }

    public function updateCourt(Request $request, int $court)
    {
        $venue = $request->user()->venue;
        $record = $venue->courts()->findOrFail($court);
        $record->update($this->courtData($request, $venue, $court));

        return $this->saved('Court details saved.');
    }

    public function addStaff(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:72'],
        ]);
        $request->user()->venue->users()->create($data + ['role' => 'staff']);

        return $this->saved('Staff account added.');
    }

    public function removeStaff(Request $request, int $staff)
    {
        $request->user()->venue->users()->where('role', 'staff')->findOrFail($staff)->delete();

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
            'rate' => ['required', 'regex:/^[0-9]{1,6}(?:\.[0-9]{1,2})?$/D'],
            'active' => ['required', 'boolean'],
        ]);
        [$pesos, $centavos] = array_pad(explode('.', $data['rate'], 2), 2, '');

        return [
            'name' => $data['name'],
            'hourly_rate' => (int) $pesos * 100 + (int) str_pad($centavos, 2, '0'),
            'active' => $data['active'],
        ];
    }

    private function saved(string $status)
    {
        return redirect()->route('settings')->with('status', $status);
    }
}
