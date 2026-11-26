<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CourtBlock;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2030, 1, 10)->setTime(0, 0));
        $this->seed();
        $this->venue = Venue::firstOrFail();
    }

    public function test_guest_can_book_and_see_a_private_confirmation(): void
    {
        $this->get(route('book', [
            'date' => '2030-01-11',
            'court' => $this->venue->courts()->first()->id,
            'start' => '19:00',
        ]))->assertOk()->assertSee('Add your details');
        $this->get(route('book.confirmed'))->assertRedirect(route('book'));
        $this->post(route('book.store'), $this->bookingData())->assertRedirect(route('book.confirmed'));

        $booking = Booking::firstOrFail();
        $this->assertSame('11:00', $booking->starts_at->format('H:i'));
        $this->assertNull($booking->paid_at);
        $this->get(route('book.confirmed'))->assertOk()
            ->assertSee(strtoupper($booking->reference))->assertSee('Due at venue')->assertDontSee('09171234567');
    }

    public function test_survey_shows_one_question_at_a_time_and_filters_courts_by_start(): void
    {
        $date = '2030-01-11';
        $court = $this->venue->courts()->firstOrFail();
        $base = ['date' => $date, 'duration' => 60];
        $this->get(route('book'))->assertOk()
            ->assertSee('Step 1 of 6')->assertSee('When do you want to play?')->assertDontSee('Add your details');
        $this->get(route('book', ['step' => 2] + $base))->assertOk()
            ->assertSee('Step 2 of 6')->assertSee('How long do you want to play?');
        $this->get(route('book', ['step' => 3] + $base))->assertOk()
            ->assertSee('Step 3 of 6')->assertSee('What time do you want to start?');
        $this->get(route('book', ['step' => 4, 'start' => '19:00'] + $base))->assertOk()
            ->assertSee('Step 4 of 6')->assertSee('Which court do you prefer?')->assertSee($court->name)->assertSee('₱400.00');
        $this->get(route('book', ['step' => 5, 'start' => '19:00', 'court' => $court->id] + $base))->assertOk()
            ->assertSee('Step 5 of 6')->assertSee('Who is this booking for?')->assertSee('Add your details')->assertSee('quoted_total');
        $this->get(route('book', ['step' => 4, 'start' => '00:00'] + $base))->assertOk()
            ->assertSee('Step 3 of 6')->assertDontSee('Add your details');
        $this->assertDatabaseCount('bookings', 0);
        $this->post(route('book.store'), $this->bookingData())->assertRedirect(route('book.confirmed'));
        $this->get(route('book', ['step' => 4, 'start' => '19:00'] + $base))->assertOk()
            ->assertDontSee($court->name);
        $this->get(route('book.confirmed'))->assertOk()->assertSee('Step 6 of 6')->assertSee('Your court is booked!');
    }

    public function test_booking_durations_from_one_to_five_hours(): void
    {
        $this->assertSame([60, 90, 120, 150, 180, 210, 240, 270, 300], $this->venue->durations());
        $this->get(route('book', ['step' => 2, 'date' => '2030-01-11']))
            ->assertOk()->assertSee('1 hour')->assertSee('1 hour and 30 minutes')
            ->assertSee('2 hours and 30 minutes')->assertSee('4 hours and 30 minutes')->assertSee('5 hours');

        foreach ([30, 75, 330] as $invalid) {
            $this->post(route('book.store'), array_replace($this->bookingData(), ['duration' => $invalid]))
                ->assertSessionHasErrors('duration');
        }

        $booking = array_replace($this->bookingData(), [
            'start' => '17:00', 'duration' => 300, 'quoted_total' => 200000,
        ]);
        $this->post(route('book.store'), array_replace($booking, ['start' => '17:30']))
            ->assertSessionHasErrors('start');
        $this->post(route('book.store'), $booking)->assertRedirect(route('book.confirmed'));
        $this->assertSame(200000, Booking::firstOrFail()->total);
        $this->assertSame(300, (int) Booking::firstOrFail()->starts_at->diffInMinutes(Booking::firstOrFail()->ends_at));
    }

    public function test_existing_venue_duration_settings_are_reset_to_the_new_defaults(): void
    {
        $this->venue->update(['booking_durations' => [30, 60, 120]]);
        $migration = require database_path('migrations/2026_11_26_061500_expand_booking_durations.php');
        $migration->up();
        $this->assertSame([60, 90, 120, 150, 180, 210, 240, 270, 300], $this->venue->fresh()->durations());
    }

    public function test_conflicting_bookings_fail_and_back_to_back_bookings_fit(): void
    {
        $this->post(route('book.store'), $this->bookingData())->assertRedirect(route('book.confirmed'));
        $this->post(route('book.store'), array_replace($this->bookingData(), ['start' => '19:30']))
            ->assertSessionHasErrors('start');
        $this->post(route('book.store'), array_replace($this->bookingData(), ['start' => '20:00']))
            ->assertRedirect(route('book.confirmed'));
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_staff_can_block_release_book_mark_paid_and_cancel(): void
    {
        $staff = $this->signInAs('staff');
        $this->get(route('staff.calendar', ['date' => '2030-01-11', 'action' => 'book']))->assertOk()->assertSee('Add a booking');

        $data = $this->bookingData();
        $this->post(route('staff.block'), $data + ['end' => '20:00', 'reason' => 'Maintenance'])->assertRedirect();
        $this->post(route('book.store'), $data)->assertSessionHasErrors('start');
        $block = CourtBlock::firstOrFail();
        $this->get(route('staff.calendar', ['date' => $data['date']]))->assertOk()->assertSee('Maintenance');
        $this->delete(route('staff.unblock', $block->id))->assertRedirect();
        $this->post(route('staff.book'), $data)->assertRedirect();
        $booking = Booking::firstOrFail();
        $this->assertSame('staff', $booking->source);

        $this->patch(route('staff.paid', $booking->id))->assertRedirect();
        $this->get(route('staff.calendar', ['date' => $data['date']]))->assertOk()->assertSee('Paid')->assertSee('Sample Player');
        $this->patch(route('staff.cancel', $booking->id))->assertRedirect();
        $this->assertNotNull($booking->fresh()->cancelled_at);
        $this->assertNotNull($booking->fresh()->paid_at);
        $this->post(route('book.store'), $data)->assertRedirect(route('book.confirmed'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_owner_rate_updates_preserve_prior_totals_and_require_a_fresh_quote(): void
    {
        $data = $this->bookingData();
        $this->post(route('book.store'), $data)->assertRedirect();
        $this->signInAs('owner');

        $this->get(route('settings'))->assertOk()->assertSee('Weekly opening hours');
        $this->patch(route('settings.court.update', $data['court_id']), [
            'name' => 'Court 1', 'rate' => '475.50', 'active' => '1',
        ])->assertRedirect(route('settings'));

        $next = array_replace($data, ['start' => '20:00']);
        $this->post(route('book.store'), $next)->assertSessionHasErrors('start');
        $this->post(route('book.store'), array_replace($next, ['quoted_total' => 47550]))->assertRedirect(route('book.confirmed'));
        $this->assertSame([40000, 47550], Booking::orderBy('id')->pluck('total')->all());
    }

    public function test_permissions_and_hours_apply_to_normal_routes(): void
    {
        $this->get(route('staff.calendar'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => 'wrong@example.test', 'password' => 'bad'])->assertSessionHasErrors('email');
        $this->signInAs('staff');
        $this->get(route('settings'))->assertForbidden();
        $this->post(route('staff.book'), array_replace($this->bookingData(), ['start' => '22:00']))
            ->assertSessionHasErrors('start');

        $this->signInAs('owner');
        $hours = array_fill(0, 7, ['opens_at' => '07:00', 'closes_at' => '24:00']);
        $hours[5]['closed'] = '1';
        $this->patch(route('settings.hours'), ['hours' => $hours])->assertRedirect(route('settings'));
        $this->post(route('book.store'), $this->bookingData())->assertSessionHasErrors('start');
    }

    public function test_owner_can_create_and_revoke_staff_login(): void
    {
        $this->signInAs('owner');
        $credentials = ['email' => 'scheduler@example.test', 'password' => 'trial-password-1'];
        $this->post(route('settings.staff.add'), $credentials + ['name' => 'Scheduler'])->assertRedirect(route('settings'));
        $staff = $this->venue->users()->where('email', $credentials['email'])->firstOrFail();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), $credentials)->assertRedirect(route('staff.calendar'));
        $this->assertAuthenticatedAs($staff);

        $this->signInAs('owner');
        $this->delete(route('settings.staff.remove', $staff->id))->assertRedirect(route('settings'));
        $this->post(route('logout'));
        $this->post(route('login.store'), $credentials)->assertSessionHasErrors('email');
    }

    private function signInAs(string $role)
    {
        $user = $this->venue->users()->create([
            'name' => ucfirst($role),
            'email' => uniqid($role).'@example.test',
            'password' => 'local-password',
            'role' => $role,
        ]);
        $this->actingAs($user);

        return $user;
    }

    private function bookingData(): array
    {
        return [
            'court_id' => $this->venue->courts()->firstOrFail()->id,
            'date' => '2030-01-11',
            'start' => '19:00',
            'duration' => 60,
            'quoted_total' => 40000,
            'customer_name' => 'Sample Player',
            'customer_phone' => '09171234567',
        ];
    }
}
