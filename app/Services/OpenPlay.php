<?php

namespace App\Services;

use App\Models\CourtBlock;
use App\Models\OpenPlayRegistration;
use App\Models\OpenPlaySession;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OpenPlay
{
    public function __construct(private Schedule $schedule, private EmvPaymentQr $paymentQr) {}

    public function create(Venue $venue, array $data): array
    {
        return DB::transaction(function () use ($venue, $data) {
            $venue = Venue::query()->sharedLock()->findOrFail($venue->id);
            TrialAccess::ensure($venue);
            $courts = $venue->courts()->whereIn('id', $data['court_ids'])->orderBy('id')->lockForUpdate()->get();
            if ($courts->count() !== count($data['court_ids']) || $courts->contains(fn ($court) => ! $court->active)) {
                $this->reject('court_ids', 'Choose active courts at this venue.');
            }

            $repeat = $data['repeat'] ?? 'none';
            $count = $repeat === 'none' ? 1 : (int) ($data['occurrences'] ?? 1);
            $series = $count > 1 ? (string) Str::ulid() : null;
            $first = CarbonImmutable::parse($data['date'], $venue->timezone)->startOfDay();
            $sessions = [];
            for ($i = 0; $i < $count; $i++) {
                $date = $first->addDays($i * ($repeat === 'weekly' ? 7 : 1))->toDateString();
                $start = $this->schedule->time($venue, $date, $data['start']);
                $end = $this->schedule->time($venue, $date, $data['end']);
                $hours = $this->schedule->hours($venue, $date);
                if ($start->lte(now()) || $end->lte($start) || ! $hours || $hours->closed
                    || $start->lt($this->schedule->time($venue, $date, $hours->opens_at))
                    || $end->gt($this->schedule->time($venue, $date, $hours->closes_at))
                    || $start->setTimezone($venue->timezone)->format('Y-m-d H:i') !== $date.' '.$data['start']) {
                    $this->reject('start', 'Session must fit future opening hours: '.$date.'.');
                }
                foreach ($courts as $court) {
                    if ($this->schedule->overlaps($court->bookings(), $start, $end)->occupying()->exists()
                        || $this->schedule->overlaps($court->blocks(), $start, $end)->exists()) {
                        $this->reject('court_ids', $court->name.' is unavailable on '.$date.'. No sessions were created.');
                    }
                }
                $session = $venue->openPlaySessions()->create([
                    'name' => $data['name'], 'starts_at' => $start, 'ends_at' => $end,
                    'capacity' => $data['capacity'], 'price' => Pricing::centavos($data['price']),
                    'skill_level' => $data['skill_level'], 'series_id' => $series,
                ]);
                $session->courts()->attach($courts->modelKeys());
                foreach ($courts as $court) {
                    $court->blocks()->create([
                        'open_play_session_id' => $session->id, 'starts_at' => $start,
                        'ends_at' => $end, 'reason' => 'Open Play: '.$session->name,
                    ]);
                }
                $sessions[] = $session;
            }

            return $sessions;
        }, 3);
    }

    public function register(Venue $venue, int $sessionId, array $data, string $source): OpenPlayRegistration
    {
        return DB::transaction(function () use ($venue, $sessionId, $data, $source) {
            $venue = Venue::query()->sharedLock()->findOrFail($venue->id);
            TrialAccess::ensure($venue);
            if ($source === 'online' && !$venue->acceptsPublicBookings()) {
                $this->reject('session', 'This venue is not accepting online registrations.');
            }
            $legal = $source === 'online' ? LegalPolicies::context($venue,lock:true) : null;
            if ($legal) LegalPolicies::validate($data,$legal);
            $session = $venue->openPlaySessions()->lockForUpdate()->findOrFail($sessionId);
            $cutoff = $source === 'staff' ? $session->ends_at : $session->starts_at;
            if ($session->cancelled_at || $cutoff->lte(now())) {
                $this->reject('session', 'This Open Play session is no longer accepting registrations.');
            }
            if ($source === 'online' && $session->starts_at->setTimezone($venue->timezone)->toDateString() >
                CarbonImmutable::today($venue->timezone)->addDays($venue->advance_days)->toDateString()) {
                $this->reject('session', 'This session is outside the online booking window.');
            }
            if ($session->spotsLeft() < 1) {
                $this->reject('session', 'This session is full. Choose another session.');
            }
            if (isset($data['quoted_total']) && (int) $data['quoted_total'] !== $session->price) {
                $this->reject('session', 'The price changed. Review the session before joining.');
            }
            if (! $venue->qr_payments_enabled || ! $venue->payment_qr_base || ! $venue->payment_messenger_url) {
                $this->reject('session', 'The venue must set up QR payments before accepting Open Play registrations.');
            }
            try {
                $payload = $this->paymentQr->generate($venue->payment_qr_base, $session->price);
            } catch (InvalidArgumentException $exception) {
                $this->reject('session', $exception->getMessage());
            }

            $registration = $session->registrations()->create([
                'reference' => (string) Str::ulid(), 'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'], 'total' => $session->price, 'source' => $source,
                'payment_token' => Str::random(48), 'payment_qr_payload' => $payload,
                'payment_messenger_url' => $venue->payment_messenger_url,
                'payment_expires_at' => min(now()->toImmutable()->addMinutes(20), $cutoff),
                'cancellation_policy' => LegalPolicies::venuePolicy($venue),
            ]);
            if ($legal) LegalPolicies::record($legal,'open_play',$registration->id,$venue->id);
            if ($source==='online') {
                VenueNotifications::emit($venue,'open_play','registered-'.$registration->id,'New Open Play registration','A player reserved a spot in '.$session->name.'.','open-play');
                VenueNotifications::emit($venue,'payment','open-play-pending-'.$registration->id,'Open Play payment awaiting verification','A new player hold is awaiting payment.','open-play');
            }
            return $registration;
        }, 3);
    }

    public function updateRegistration(Venue $venue, int $sessionId, int $id, string $action, array $data, int $staffId): void
    {
        DB::transaction(function () use ($venue, $sessionId, $id, $action, $data, $staffId) {
            $venue = Venue::query()->sharedLock()->findOrFail($venue->id);
            $session = $venue->openPlaySessions()->lockForUpdate()->findOrFail($sessionId);
            $record = $session->registrations()->lockForUpdate()->findOrFail($id);
            if ($action === 'cancel') {
                if (! $record->cancelled_at) {
                    $record->cancelled_at = now();
                    $record->save();
                    VenueNotifications::emit($venue,'open_play','cancelled-'.$record->id,'Open Play registration cancelled','A player reservation was cancelled.','open-play');
                }

                return;
            }
            if ($session->cancelled_at || $record->cancelled_at) {
                $this->reject('registration', 'This registration was cancelled.');
            }
            if ($action === 'paid' && ! $record->paid_at) {
                if ($record->paymentExpired()) {
                    $this->reject('registration', 'This hold expired. Reconcile any late payment in Messenger.');
                }
                if (Pricing::centavos($data['received_amount']) !== $record->total) {
                    $this->reject('received_amount', 'The received amount must match the player fee.');
                }
                $record->payment_reference = PaymentReferences::claim($data['payment_reference']);
                $record->payment_verified_by = $staffId;
                $record->paid_at = now();
                VenueNotifications::emit($venue,'payment','open-play-paid-'.$record->id,'Open Play payment verified','A player payment was verified.','open-play');
            }
            if ($action === 'check-in') {
                if (! $record->paid_at || $session->ends_at->lte(now())) {
                    $this->reject('registration', 'Only paid players in an active session can check in.');
                }
                $record->checked_in_at ??= now();
            }
            $record->save();
        }, 3);
    }

    public function cancelSession(Venue $venue, int $id): void
    {
        DB::transaction(function () use ($venue, $id) {
            $venue = Venue::query()->sharedLock()->findOrFail($venue->id);
            $session = $venue->openPlaySessions()->lockForUpdate()->findOrFail($id);
            if ($session->cancelled_at) {
                return;
            }
            if ($session->starts_at->lte(now())) {
                $this->reject('session', 'Only upcoming sessions can be cancelled.');
            }
            $venue->courts()->whereIn('id', $session->courts()->pluck('courts.id'))->orderBy('id')->lockForUpdate()->get();
            $session->cancelled_at = now();
            VenueNotifications::emit($venue,'open_play','session-cancelled-'.$session->id,'Open Play cancelled','An Open Play session was cancelled.','open-play');
            $session->save();
            $session->registrations()->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
            CourtBlock::query()->where('open_play_session_id', $session->id)->delete();
        }, 3);
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
