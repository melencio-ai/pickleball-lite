<?php

namespace Tests\Feature;

use App\Models\Venue;
use App\Services\OpenPlay;
use App\Services\Pricing;
use App\Services\VenueWebsite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VenueWebsiteTest extends TestCase
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

    public function test_home_and_booking_routes_are_separate_and_legacy_links_survive(): void
    {
        $this->get('/')->assertOk()->assertSee('Your next game starts here.')->assertSee($this->venue->name)
            ->assertSee('Opening hours')->assertSee('Court pricing')->assertSee('Upcoming Open Play')
            ->assertSee('₱400.00')->assertSee('3 pickleball courts')->assertDontSee('Step 1 of 6')
            ->assertDontSee('Free parking')->assertDontSee('Showers');
        $this->get('/?utm_source=facebook')->assertOk()->assertSee('Opening hours');
        $this->get('/book')->assertOk()->assertSee('What would you like to book?')
            ->assertSee('Book a court')->assertSee('Join Open Play')->assertDontSee('When do you want to play?');
        $this->get('/book?step=1')->assertOk()->assertSee('When do you want to play?');
        $this->get('/?date=2030-01-11&duration=90&step=2')->assertRedirect(route('book', [
            'date' => '2030-01-11', 'duration' => '90', 'step' => '2',
        ]));
        $this->get('/bookings/confirmed')->assertRedirect(route('book'));
    }

    public function test_demo_gallery_is_optimized_labeled_and_owner_can_disable_it(): void
    {
        $page = $this->get('/')->assertOk()
            ->assertSee('AI-generated demo images for illustration only.')
            ->assertSee('Good games. Great memories.')
            ->assertSee('How do I book a court?')
            ->assertSee('Can I join Open Play alone?')
            ->assertSee('Can beginners join Open Play?')
            ->assertSee('How do I know my booking is confirmed?')
            ->assertSee('Pay at the venue.')
            ->assertSee('What if my preferred time or session is full?')
            ->assertSee('How much does it cost to play?')
            ->assertSee('How long can I book a court?')
            ->assertSee('How far ahead can I book?')
            ->assertSee('Can I change or cancel my booking?');
        $this->assertSame(5, substr_count($page->getContent(), ' data-gallery-image '));
        foreach (array_keys(VenueWebsite::DEMO_GALLERY) as $image) {
            $url = route('landing.demo-image', ['image' => $image]);
            $page->assertSee($url);
            $asset = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
            $bytes = $asset->getContent();
            $this->assertSame('RIFF', substr($bytes, 0, 4));
            $this->assertSame('WEBP', substr($bytes, 8, 4));
            $this->assertLessThan(500000, strlen($bytes));
            $this->assertSame([1200, 900], array_slice(getimagesizefromstring($bytes), 0, 2));
        }
        $this->get('/demo-gallery/unknown.webp')->assertNotFound();
        $this->venue->update([
            'qr_payments_enabled' => true,
            'payment_qr_base' => '00020101021126320014PH.PPMI.TESTQR0110TESTVENUE15204479953036085802PH5910LITE COURT6006MANILA63041052',
            'payment_messenger_url' => 'https://m.me/litecourts',
        ]);
        $this->get('/')->assertOk()->assertSee('Pay using the QR code and send your receipt in Messenger.')
            ->assertSee('Wait for the venue to check your payment.');
        $this->signIn('owner');
        $this->patch(route('settings.website.save'), ['show_demo_gallery' => '0'])
            ->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertDontSee('AI-generated demo images')->assertDontSee('data-gallery-image');
        $this->patch(route('settings.website.save'), ['headline' => 'Updated website'])->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertDontSee('data-gallery-image');
        $this->patch(route('settings.website.save'), ['show_demo_gallery' => '1'])->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertSee('AI-generated demo images');
    }

    public function test_owner_content_facilities_and_gallery_are_public_and_safe(): void
    {
        $this->get(route('settings.website'))->assertRedirect(route('login'));
        $this->signIn('owner');
        $this->get(route('settings.website'))->assertOk()->assertSee('Venue photo gallery');
        $this->patch(route('settings.website.save'), [
            'headline' => 'Play together at Lite Courts',
            'description' => 'Come for your next game.',
            'introduction' => '<script>bad()</script>',
            'facilities' => ['parking', 'lighting'],
            'custom_facilities' => "Water station\nWater station\nPro shop",
            'faq_questions' => ['Do you rent paddles?'],
            'faq_answers' => ['Ask our staff.'],
            'seo_title' => 'Lite Courts | Play together',
            'seo_description' => 'Reserve your court today.',
        ])->assertSessionHasNoErrors()->assertRedirect(route('settings.website'));
        $photo = UploadedFile::fake()->createWithContent('court.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZ0AAAAASUVORK5CYII='
        ));
        $this->patch(route('settings.website.gallery', 'gallery1'), ['photo' => $photo])->assertSessionHasNoErrors();
        $media = $this->venue->media()->where('kind', 'gallery1')->firstOrFail();
        $image = route('book.image', ['kind' => 'gallery1', 'version' => $media->version]);
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/')->assertOk()->assertSee('Play together at Lite Courts')->assertSee('Free parking')
            ->assertSee('Night lighting')->assertSee('Water station')->assertSee('Pro shop')
            ->assertSee('Do you rent paddles?')->assertSee('Reserve your court today.')->assertSee($image)
            ->assertDontSee('AI-generated demo images')->assertDontSee('/demo-gallery/courts.webp')
            ->assertSee('<script>bad()</script>')->assertDontSee('<script>bad()</script>', false)
            ->assertDontSee('Showers');
        $this->patch(route('settings.website.save'), ['facilities' => ['invented']])->assertSessionHasErrors('facilities.0');
        $this->patch(route('settings.website.save'), ['faq_answers' => ['Answer only']])->assertSessionHasErrors('faqs');
        $this->patch(route('settings.website.gallery', 'gallery1'), ['remove' => '1'])->assertSessionHasNoErrors();
        $this->get($image)->assertNotFound();
        $this->signIn('staff');
        $this->get(route('settings.website'))->assertForbidden();
        $this->patch(route('settings.website.save'), ['headline' => 'Not allowed'])->assertForbidden();
        $this->patch(route('settings.website.gallery', 'gallery1'), ['remove' => '1'])->assertForbidden();
    }

    public function test_rates_and_open_play_use_the_actual_venue_configuration(): void
    {
        $court = $this->venue->courts()->firstOrFail();
        $court->update(['surface_type' => 'indoor']);
        $this->venue->rates()->create(['weekdays' => [5], 'starts_at' => '18:00', 'ends_at' => '20:00', 'hourly_rate' => 60000]);
        $this->venue->rates()->create(['court_id' => $court->id, 'weekdays' => [5], 'starts_at' => '19:30', 'ends_at' => '20:00', 'hourly_rate' => 80000]);
        $this->venue->closures()->create(['date' => '2030-01-12', 'reason' => 'Holiday closure']);
        $rateData = app(VenueWebsite::class)->rates($this->venue, $this->venue->courts()->where('active', true)->get(), $this->venue->hours()->get()->keyBy('weekday'), app(Pricing::class));
        $first = collect($rateData['groups'])->first(fn ($group) => in_array($court->name, $group['courts'], true));
        $this->assertContains(['start' => 1170, 'end' => 1200, 'rate' => 80000, 'days' => [5]], $first['windows']);
        $this->get(route('book', ['date' => '2030-01-11', 'court' => $court->id, 'start' => '19:00']))
            ->assertOk()->assertSee('₱700.00');
        $this->venue->update([
            'qr_payments_enabled' => true,
            'payment_qr_base' => '00020101021126320014PH.PPMI.TESTQR0110TESTVENUE15204479953036085802PH5910LITE COURT6006MANILA63041052',
            'payment_messenger_url' => 'https://m.me/litecourts',
        ]);
        $session = app(OpenPlay::class)->create($this->venue, [
            'name' => 'Friday Play', 'date' => '2030-01-11', 'start' => '15:00', 'end' => '16:00',
            'court_ids' => [$court->id], 'capacity' => 8, 'price' => '200.00',
            'skill_level' => 'beginner', 'repeat' => 'none',
        ])[0];
        $this->get('/')->assertOk()->assertSee('₱600.00')->assertSee('₱800.00')->assertSee('Holiday closure')
            ->assertSee('1 indoor court')->assertSee('Friday Play')->assertSee('8 spots left')
            ->assertSee('₱200.00')->assertSee(route('open-play', ['date' => '2030-01-11', 'session' => $session->id]))
            ->assertDontSee($this->venue->payment_qr_base);
        app(OpenPlay::class)->cancelSession($this->venue, $session->id);
        $this->get('/')->assertOk()->assertDontSee('Friday Play');
    }

    private function signIn(string $role): void
    {
        $this->actingAs($this->venue->users()->firstOrCreate(['email' => 'website-'.$role.'@example.test'], [
            'name' => ucfirst($role), 'password' => 'test-password-123', 'role' => $role,
        ]));
    }
}
