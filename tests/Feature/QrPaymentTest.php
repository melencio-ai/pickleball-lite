<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Venue;
use App\Services\EmvPaymentQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '00020101021126320014PH.PPMI.TESTQR0110TESTVENUE15204479953036085802PH5910LITE COURT6006MANILA63041052';

    private const EXPECTED = '00020101021226320014PH.PPMI.TESTQR0110TESTVENUE15204479953036085406400.005802PH5910LITE COURT6006MANILA63041D5C';

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 10)->setTime(0, 0));
        $this->seed();
        $this->venue = Venue::firstOrFail();
    }

    public function test_qr_generation_matches_an_independent_crc_and_amount_fixture(): void
    {
        $generator = app(EmvPaymentQr::class);
        $this->assertSame(self::EXPECTED, $generator->generate(self::BASE, 40000));
        $this->expectException(\InvalidArgumentException::class);
        $generator->generate(substr(self::BASE, 0, -1).'3', 40000);
    }

    public function test_booking_needs_staff_to_verify_exact_payment_from_messenger(): void
    {
        $owner = $this->venue->users()->create([
            'name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'sample-password', 'role' => 'owner',
        ]);
        $this->actingAs($owner);
        $settings = [
            'qr_payments_enabled' => '1',
            'payment_qr_base' => self::BASE,
            'payment_messenger_url' => 'https://m.me/litecourts',
        ];
        $this->patch(route('settings.payments'), $settings)->assertSessionHasErrors('provider_confirmed');
        $this->patch(route('settings.payments'), $settings + ['provider_confirmed' => '1'])->assertSessionHasNoErrors();

        $this->post(route('book.store'), $this->booking())->assertRedirect();
        $booking = Booking::firstOrFail();
        $this->assertSame(self::EXPECTED, $booking->payment_qr_payload);
        $this->assertNull($booking->paid_at);
        $this->get(route('book.payment', $booking->payment_token))->assertOk()
            ->assertSee('Send receipt in Messenger')->assertSee('https://m.me/litecourts')
            ->assertDontSee($booking->customer_phone);
        $this->get(route('staff.calendar', ['date' => '2030-01-11']))->assertOk()->assertSee('Verify Messenger payment');

        $proof = ['received_amount' => '400.00', 'payment_reference' => 'AB-123', 'verified_in_messenger' => '1'];
        $this->patch(route('staff.paid', $booking->id))->assertSessionHasErrors('verified_in_messenger');
        $this->patch(route('staff.paid', $booking->id), array_replace($proof, ['received_amount' => '399.00']))
            ->assertSessionHasErrors('received_amount');
        $this->patch(route('staff.paid', $booking->id), $proof)->assertSessionHasNoErrors();
        $this->get(route('book.payment', $booking->payment_token))->assertOk()
            ->assertSee('Payment verified')->assertDontSee('data-payment-qr');
        $this->assertSame('AB-123', $booking->fresh()->payment_reference);

        $otherCourt = $this->venue->courts()->orderBy('id')->skip(1)->first();
        $this->post(route('staff.book'), array_replace($this->booking(), ['court_id' => $otherCourt->id]))->assertRedirect();
        $other = Booking::orderByDesc('id')->first();
        $this->assertNotNull($other->payment_token);
        $this->patch(route('staff.paid', $other->id), array_replace($proof, ['payment_reference' => 'ab-123']))
            ->assertSessionHasErrors('payment_reference');
        $this->assertNull($other->fresh()->paid_at);
    }

    public function test_expired_holds_release_the_slot_and_cannot_be_verified(): void
    {
        $this->venue->update([
            'qr_payments_enabled' => true, 'payment_qr_base' => self::BASE,
            'payment_messenger_url' => 'https://m.me/litecourts',
        ]);
        $this->post(route('book.store'), $this->booking())->assertRedirect();
        $original = Booking::firstOrFail();
        $this->post(route('book.store'), $this->booking())->assertSessionHasErrors('start');
        $this->travel(21)->minutes();
        $this->get(route('book.payment', $original->payment_token))->assertOk()
            ->assertSee('Payment hold expired')->assertDontSee('data-payment-qr');
        $this->post(route('book.store'), $this->booking())->assertRedirect();
        $this->assertDatabaseCount('bookings', 2);

        $this->actingAs($this->venue->users()->create([
            'name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'sample-password', 'role' => 'staff',
        ]));
        $this->patch(route('staff.paid', $original->id), [
            'received_amount' => '400.00', 'payment_reference' => 'LATE-123', 'verified_in_messenger' => '1',
        ])->assertSessionHasErrors('booking');
        $this->assertNull($original->fresh()->paid_at);
    }

    private function booking(): array
    {
        return [
            'court_id' => $this->venue->courts()->firstOrFail()->id,
            'date' => '2030-01-11', 'start' => '19:00', 'duration' => 60, 'quoted_total' => 40000,
            'customer_name' => 'Sample Player', 'customer_phone' => '09171234567',
        ];
    }
}
