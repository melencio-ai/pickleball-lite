<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CourtBlock;
use App\Models\OpenPlayRegistration;
use App\Models\OpenPlaySession;
use App\Models\Venue;
use App\Services\EmvPaymentQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenPlayTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '00020101021126320014PH.PPMI.TESTQR0110TESTVENUE15204479953036085802PH5910LITE COURT6006MANILA63041052';
    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 10)->setTime(0, 0));
        $this->seed();
        $this->venue = Venue::firstOrFail();
        $this->venue->update([
            'qr_payments_enabled' => true, 'payment_qr_base' => self::BASE,
            'payment_messenger_url' => 'https://m.me/litecourts',
        ]);
    }

    public function test_staff_publish_sessions_with_court_blocks_and_private_bookings_cannot_overlap(): void
    {
        $this->get(route('staff.open-play'))->assertRedirect(route('login'));
        $this->signIn();
        $this->get(route('staff.open-play'))->assertOk()->assertSee('Create Open Play');
        $session = $this->publish(['court_ids' => $this->venue->courts()->pluck('id')->take(2)->all()]);
        $this->assertDatabaseCount('court_blocks', 2);
        $this->assertSame(2, $session->courts()->count());
        $this->get(route('staff.open-play.show', $session->id))->assertOk()->assertSee('Player roster');
        $this->get(route('staff.calendar', ['date' => '2030-01-11']))->assertOk()->assertSee('Manage session')->assertSee('Evening Open Play');
        $this->post(route('book.store'), $this->booking())->assertSessionHasErrors('start');
        $this->post(route('staff.open-play.store'), $this->sessionData())->assertSessionHasErrors('court_ids');
        $block = CourtBlock::firstOrFail();
        $this->delete(route('staff.unblock', $block->id))->assertSessionHasErrors('block');
        $this->patch(route('staff.open-play.cancel', $session->id))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('court_blocks', 0);
        $this->assertNotNull($session->fresh()->cancelled_at);
        $this->post(route('book.store'), $this->booking())->assertSessionHasNoErrors();
    }

    public function test_guest_survey_payment_hold_verification_and_check_in(): void
    {
        $this->signIn();
        $session = $this->publish(['capacity' => 1]);
        auth()->logout();
        $this->get(route('book'))->assertOk()->assertSee('Join Open Play');
        $this->get(route('open-play'))->assertOk()->assertSee('Step 1 of 4')->assertSee('When do you want to play?');
        $this->get(route('open-play', ['date' => '2030-01-11']))->assertOk()
            ->assertSee('Step 2 of 4')->assertSee('Evening Open Play')->assertSee('1 spot left');
        $this->get(route('open-play', ['date' => '2030-01-11', 'session' => $session->id]))->assertOk()
            ->assertSee('Step 3 of 4')->assertSee('Who is joining?');
        $this->post(route('open-play.join', $session->id), array_replace($this->player(), ['quoted_total' => 19900]))
            ->assertSessionHasErrors('session');
        $this->post(route('open-play.join', $session->id), $this->player())->assertRedirect();
        $player = OpenPlayRegistration::firstOrFail();
        $this->assertSame(20000, $player->total);
        $this->assertSame(app(EmvPaymentQr::class)->generate(self::BASE, 20000), $player->payment_qr_payload);
        $this->assertSame(0, $session->fresh()->spotsLeft());
        $this->get(route('open-play.payment', $player->payment_token))->assertOk()
            ->assertSee('Step 4 of 4')->assertSee('Ready to join?')->assertSee('Player spot held for')->assertSee('20:00')
            ->assertSee('Send receipt in Messenger')->assertDontSee($player->customer_phone);
        $this->getJson(route('open-play.payment.status', $player->payment_token))->assertExactJson(['status' => 'pending']);
        $this->post(route('open-play.join', $session->id), $this->player())->assertSessionHasErrors('session');
        $this->signIn();
        $this->patch(route('staff.open-play.action', [$session->id, $player->id, 'check-in']))->assertSessionHasErrors('registration');
        $this->patch(route('staff.open-play.action', [$session->id, $player->id, 'paid']), array_replace($this->proof(), ['received_amount' => '199.00']))
            ->assertSessionHasErrors('received_amount');
        $this->patch(route('staff.open-play.action', [$session->id, $player->id, 'paid']), $this->proof())->assertSessionHasNoErrors();
        $this->getJson(route('open-play.payment.status', $player->payment_token))->assertExactJson(['status' => 'confirmed']);
        $this->get(route('open-play.payment', $player->payment_token))->assertOk()->assertSee('You are registered!')->assertDontSee('data-payment-qr');
        $this->patch(route('staff.open-play.action', [$session->id, $player->id, 'check-in']))->assertSessionHasNoErrors();
        $this->assertNotNull($player->fresh()->checked_in_at);
        $this->assertSame('OPEN-123', $player->fresh()->payment_reference);
    }

    public function test_expired_player_holds_release_capacity_but_not_courts(): void
    {
        $this->signIn();
        $session = $this->publish(['capacity' => 1]);
        $this->post(route('open-play.join', $session->id), $this->player())->assertRedirect();
        $player = OpenPlayRegistration::firstOrFail();
        $this->travel(21)->minutes();
        $this->getJson(route('open-play.payment.status', $player->payment_token))->assertExactJson(['status' => 'expired']);
        $this->get(route('open-play.payment', $player->payment_token))->assertOk()->assertSee('Your player spot was released');
        $this->assertSame(1, $session->fresh()->spotsLeft());
        $this->assertDatabaseCount('court_blocks', 1);
        $this->patch(route('staff.open-play.action', [$session->id, $player->id, 'paid']), $this->proof())->assertSessionHasErrors('registration');
        $this->post(route('staff.open-play.register', $session->id), $this->player())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('open_play_registrations', 2);
        $this->patch(route('staff.open-play.cancel', $session->id))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('court_blocks', 0);
        $this->assertSame(2, $session->registrations()->whereNotNull('cancelled_at')->count());
    }

    public function test_payment_references_are_unique_across_court_and_player_bookings(): void
    {
        $this->signIn();
        $session = $this->publish();
        $this->post(route('open-play.join', $session->id), $this->player())->assertRedirect();
        $player = OpenPlayRegistration::firstOrFail();
        $otherCourt = $this->venue->courts()->orderBy('id')->skip(1)->firstOrFail();
        $this->post(route('book.store'), array_replace($this->booking(), ['court_id' => $otherCourt->id]))->assertRedirect();
        $booking = Booking::firstOrFail();
        $this->patch(route('staff.paid', $booking->id), array_replace($this->proof(), ['received_amount' => '400.00']))->assertSessionHasNoErrors();
        $this->patch(route('staff.open-play.action', [$session->id, $player->id, 'paid']), array_replace($this->proof(), ['payment_reference' => 'open-123']))
            ->assertSessionHasErrors('payment_reference');
        $this->assertNull($player->fresh()->paid_at);
        $this->patch(route('staff.open-play.action', [$session->id, $player->id, 'paid']), array_replace($this->proof(), ['payment_reference' => 'OPEN-456']))
            ->assertSessionHasNoErrors();
        $this->post(route('staff.book'), array_replace($this->booking(), ['court_id' => $otherCourt->id, 'start' => '20:00']))->assertSessionHasNoErrors();
        $otherBooking = Booking::orderByDesc('id')->firstOrFail();
        $this->patch(route('staff.paid', $otherBooking->id), array_replace($this->proof(), ['received_amount' => '400.00', 'payment_reference' => 'open-456']))
            ->assertSessionHasErrors('payment_reference');
    }

    public function test_recurring_series_is_atomic_and_settings_cannot_release_assigned_courts(): void
    {
        $this->signIn();
        $this->post(route('book.store'), array_replace($this->booking(), ['date' => '2030-01-12']))->assertRedirect();
        $this->post(route('staff.open-play.store'), array_replace($this->sessionData(), ['repeat' => 'daily', 'occurrences' => 2]))
            ->assertSessionHasErrors('court_ids');
        $this->assertDatabaseCount('open_play_sessions', 0);
        $session = $this->publish(['repeat' => 'weekly', 'occurrences' => 3]);
        $this->assertDatabaseCount('open_play_sessions', 3);
        $this->assertSame(3, OpenPlaySession::where('series_id', $session->series_id)->count());
        $this->assertDatabaseCount('court_blocks', 3);
        $this->post(route('settings.closures.add'), ['date' => '2030-01-11', 'reason' => 'Holiday'])->assertSessionHasErrors('date');
        $court = $this->venue->courts()->firstOrFail();
        $this->patch(route('settings.court.update', $court->id), ['name' => $court->name, 'rate' => '400', 'active' => '0'])
            ->assertSessionHasErrors('active');
    }

    public function test_qr_disabled_and_cross_venue_access_are_rejected(): void
    {
        $this->signIn();
        $session = $this->publish();
        $this->venue->update(['qr_payments_enabled' => false]);
        $this->post(route('open-play.join', $session->id), $this->player())->assertSessionHasErrors('session');
        $other = Venue::create(['name' => 'Other venue', 'address' => '', 'phone' => '', 'timezone' => 'Asia/Manila']);
        $user = $other->users()->create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'sample-password', 'role' => 'staff']);
        $this->actingAs($user);
        $this->get(route('staff.open-play.show', $session->id))->assertNotFound();
        $this->patch(route('staff.open-play.cancel', $session->id))->assertNotFound();
    }

    private function signIn(): void
    {
        $user = $this->venue->users()->firstOrCreate(['email' => 'open-play@example.test'], [
            'name' => 'Owner', 'password' => 'sample-password', 'role' => 'owner',
        ]);
        $this->actingAs($user);
    }

    private function publish(array $overrides = []): OpenPlaySession
    {
        $this->post(route('staff.open-play.store'), array_replace($this->sessionData(), $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return OpenPlaySession::orderBy('id')->firstOrFail();
    }

    private function sessionData(): array
    {
        return [
            'name' => 'Evening Open Play', 'date' => '2030-01-11', 'start' => '18:00', 'end' => '21:00',
            'court_ids' => [$this->venue->courts()->firstOrFail()->id], 'capacity' => 24,
            'price' => '200.00', 'skill_level' => 'all', 'repeat' => 'none',
        ];
    }

    private function player(): array
    {
        return ['customer_name' => 'Sample Player', 'customer_phone' => '09171234567', 'quoted_total' => 20000];
    }

    private function booking(): array
    {
        return array_replace($this->player(), ['court_id' => $this->venue->courts()->firstOrFail()->id,
            'date' => '2030-01-11', 'start' => '19:00', 'duration' => 60, 'quoted_total' => 40000]);
    }

    private function proof(): array
    {
        return ['received_amount' => '200.00', 'payment_reference' => 'OPEN-123', 'verified_in_messenger' => '1'];
    }
}
