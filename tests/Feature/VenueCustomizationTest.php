<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VenueCustomizationTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 10)->setTime(0, 0));
        $this->seed();
        $this->venue = Venue::firstOrFail();
        $this->actingAs($this->venue->users()->create([
            'name' => 'Owner', 'email' => 'owner@example.test',
            'password' => 'sample-password', 'role' => 'owner',
        ]));
    }

    public function test_time_based_rate_changes_are_scoped_and_rechecked_when_booking(): void
    {
        $court = $this->venue->courts()->firstOrFail();
        $base = ['weekdays' => [5], 'starts_at' => '18:00', 'ends_at' => '20:00', 'rate' => '600.00'];
        $this->post(route('settings.rates.add'), $base)->assertSessionHasNoErrors();
        $this->post(route('settings.rates.add'), $base)->assertSessionHasErrors('starts_at');
        $this->post(route('settings.rates.add'), array_replace($base, [
            'court_id' => $court->id, 'starts_at' => '19:30', 'rate' => '800.00',
        ]))->assertSessionHasNoErrors();

        $query = ['date' => '2030-01-11', 'court' => $court->id, 'start' => '19:00'];
        $this->get(route('book', $query))->assertOk()->assertSee('Add your details')->assertSee('₱700.00');
        $booking = $this->bookingData();
        $this->post(route('book.store'), $booking)->assertSessionHasErrors('start');
        $this->post(route('book.store'), array_replace($booking, ['quoted_total' => 70000]))
            ->assertRedirect(route('book.confirmed'));

        $rule = $this->venue->rates()->where('court_id', $court->id)->firstOrFail();
        $this->patch(route('settings.rates.update', $rule->id), array_replace($base, [
            'court_id' => $court->id, 'starts_at' => '19:30', 'rate' => '900.00',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(70000, Booking::firstOrFail()->total);
        $this->delete(route('settings.rates.remove', $rule->id))->assertRedirect();
        $this->assertModelMissing($rule);
        $this->get(route('settings.customization'))->assertOk()->assertSee('Time-based rates');
    }

    public function test_closure_and_public_rules_apply_to_availability_and_booking(): void
    {
        $rules = [
            'booking_durations' => [90], 'advance_days' => 2, 'minimum_notice' => 120,
            'cancellation_policy' => 'Call the venue at least two hours before play.',
        ];
        $this->patch(route('settings.rules'), $rules)->assertSessionHasNoErrors();
        $booking = $this->bookingData();
        $this->post(route('book.store'), $booking)->assertSessionHasErrors('duration');
        $booking = array_replace($booking, ['duration' => 90, 'quoted_total' => 60000]);
        $this->post(route('book.store'), array_replace($booking, ['date' => '2030-01-10', 'start' => '09:30']))
            ->assertSessionHasErrors('start');
        $this->post(route('book.store'), array_replace($booking, ['date' => '2030-01-13']))
            ->assertSessionHasErrors('date');

        $closure = ['date' => $booking['date'], 'reason' => 'Holiday'];
        $this->post(route('settings.closures.add'), $closure)->assertSessionHasNoErrors();
        $this->get(route('book', ['date' => $booking['date']]))->assertOk()->assertSee('No court times are available');
        $this->post(route('book.store'), $booking)->assertSessionHasErrors('start');
        $this->delete(route('settings.closures.remove', $this->venue->closures()->firstOrFail()->id))->assertRedirect();
        $this->post(route('book.store'), $booking)->assertRedirect(route('book.confirmed'));
        $this->get(route('book.confirmed'))->assertOk()->assertSee($rules['cancellation_policy']);
        $this->post(route('settings.closures.add'), $closure)->assertSessionHasErrors('date');
    }

    public function test_owner_can_configure_the_full_new_duration_range(): void
    {
        $rules = [
            'booking_durations' => [60, 90, 120, 150, 180, 210, 240, 270, 300],
            'advance_days' => 30, 'minimum_notice' => 0,
        ];
        $this->patch(route('settings.rules'), $rules)->assertSessionHasNoErrors();
        $this->assertSame($rules['booking_durations'], $this->venue->fresh()->durations());

        foreach ([30, 75, 330] as $invalid) {
            $this->patch(route('settings.rules'), array_replace($rules, ['booking_durations' => [$invalid]]))
                ->assertSessionHasErrors('booking_durations.0');
        }
    }

    public function test_branding_images_are_served_and_customization_is_owner_only(): void
    {
        $logo = UploadedFile::fake()->createWithContent('logo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZ0AAAAASUVORK5CYII='
        ));
        $this->patch(route('settings.branding'), [
            'brand_color' => '#113355', 'map_url' => 'https://example.test/map', 'logo' => $logo,
        ])->assertSessionHasNoErrors();
        $media = $this->venue->media()->firstOrFail();
        $image = route('book.image', ['kind' => 'logo', 'version' => $media->version]);
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('book'))->assertOk()->assertSee('#113355')->assertSee('Get directions');

        $this->actingAs($this->venue->users()->create([
            'name' => 'Staff', 'email' => 'staff@example.test',
            'password' => 'sample-password', 'role' => 'staff',
        ]));
        $this->get(route('settings.customization'))->assertForbidden();
        $this->patch(route('settings.branding'), ['remove_logo' => '1'])->assertForbidden();
        $this->get($image)->assertOk();
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
