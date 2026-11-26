<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CourtBlock;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;
    private const DATE = '2030-01-11';
    private const HEADERS = ['X-Calendar-Fragment' => '1'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 10)->setTime(0, 0));
        $this->seed();
        $this->venue = Venue::firstOrFail();
    }

    public function test_calendar_keeps_details_private_and_supports_partial_refresh(): void
    {
        $this->get(route('staff.calendar.modal', ['kind' => 'new']))->assertRedirect(route('login'));
        $this->signIn();
        $data = $this->data();
        $court = $data['court_id'];
        $this->get(route('staff.calendar', ['date' => self::DATE]))->assertOk()
            ->assertSee('calendar-dialog')->assertSee('slot-'.$court.'-19:00')->assertSee('7:00 AM');
        $this->get(route('staff.calendar.modal', ['kind' => 'new', 'date' => self::DATE, 'court' => $court, 'start' => '19:00']), self::HEADERS)
            ->assertOk()->assertSee('What would you like to add?')->assertSee('Court booking')->assertSee('Open Play')->assertSee('Block time');
        $this->get(route('staff.calendar.modal', ['kind' => 'book', 'date' => self::DATE, 'court' => $court, 'start' => '19:00']), self::HEADERS)
            ->assertOk()->assertSee('Add a booking')->assertSee('value="19:00"', false)->assertSee('5 hours');
        $this->postJson(route('staff.book'), $data)->assertOk()->assertJson(['message' => 'Booking confirmed.', 'date' => self::DATE]);
        $record = Booking::firstOrFail();
        $gridUrl = route('staff.calendar', ['date' => self::DATE, 'fragment' => 'grid']);
        $this->get($gridUrl)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-calendar-grid')->assertSee('Calendar Player')->assertSee('calendar-event--unpaid')
            ->assertSee('grid-row:26 / span 2')->assertDontSee('09170000000')
            ->assertDontSee('slot-'.$court.'-19:00')->assertDontSee('slot-'.$court.'-19:30')
            ->assertSee('slot-'.$court.'-20:00')->assertDontSee('<html', false);
        $detailUrl = route('staff.calendar.modal', ['kind' => 'booking', 'record' => $record->id, 'date' => self::DATE]);
        $this->get($detailUrl, self::HEADERS)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Booking details')->assertSee('09170000000')->assertSee('Mark paid')->assertDontSee('<html', false);
        $this->get($detailUrl)->assertOk()->assertSee('Back to calendar');
        $this->patchJson(route('staff.paid', $record->id))->assertOk()->assertJson(['message' => 'Payment marked as received.']);
        $this->get($gridUrl)->assertOk()->assertSee('calendar-event--paid');
        $this->patchJson(route('staff.cancel', $record->id))->assertOk()->assertJson(['message' => 'Booking cancelled.']);
        $this->get($gridUrl)->assertOk()->assertSee('Cancelled and expired bookings')->assertSee('slot-'.$court.'-19:00');
    }

    public function test_block_removal_refresh_and_cross_venue_modal_access(): void
    {
        $this->signIn();
        $this->postJson(route('staff.block'), $this->data() + ['end' => '20:00', 'reason' => 'Maintenance'])->assertOk();
        $block = CourtBlock::firstOrFail();
        $url = route('staff.calendar.modal', ['kind' => 'blocked', 'record' => $block->id, 'date' => self::DATE]);
        $this->get($url, self::HEADERS)->assertOk()->assertSee('Maintenance')->assertSee('Remove block');
        $other = Venue::create(['name' => 'Other', 'address' => '', 'phone' => '', 'timezone' => 'Asia/Manila']);
        $this->actingAs($other->users()->create(['name' => 'Other', 'email' => 'other-calendar@example.test', 'password' => 'test-password-123', 'role' => 'staff']));
        $this->get($url, self::HEADERS)->assertNotFound();
        $this->signIn();
        $this->deleteJson(route('staff.unblock', $block->id))->assertOk()->assertJson(['message' => 'Court block removed.']);
        $this->assertModelMissing($block);
    }

    public function test_closed_day_and_expired_holds_do_not_hide_history(): void
    {
        $this->signIn();
        $this->venue->update([
            'qr_payments_enabled' => true,
            'payment_qr_base' => '00020101021126320014PH.PPMI.TESTQR0110TESTVENUE15204479953036085802PH5910LITE COURT6006MANILA63041052',
            'payment_messenger_url' => 'https://m.me/litecourts',
        ]);
        $this->postJson(route('staff.book'), $this->data())->assertOk();
        $record = Booking::firstOrFail();
        $gridUrl = route('staff.calendar', ['date' => self::DATE, 'fragment' => 'grid']);
        $this->get($gridUrl)->assertOk()->assertSee('data-hold-expires')->assertSee('Pending QR');
        $this->travel(21)->minutes();
        $this->get($gridUrl)->assertOk()->assertSee('Hold expired')->assertSee('slot-'.$record->court_id.'-19:00')->assertDontSee('data-hold-expires');
        $this->venue->closures()->create(['date' => self::DATE, 'reason' => 'Closed for holiday']);
        $this->get($gridUrl)->assertOk()->assertSee('Closed')->assertSee('Hold expired')->assertDontSee('slot-'.$record->court_id.'-19:00');
    }

    private function signIn(): void
    {
        $this->actingAs($this->venue->users()->firstOrCreate(['email' => 'calendar@example.test'], [
            'name' => 'Staff', 'password' => 'test-password-123', 'role' => 'staff',
        ]));
    }

    private function data(): array
    {
        return ['court_id' => $this->venue->courts()->firstOrFail()->id, 'date' => self::DATE,
            'start' => '19:00', 'duration' => 60, 'customer_name' => 'Calendar Player', 'customer_phone' => '09170000000'];
    }
}
