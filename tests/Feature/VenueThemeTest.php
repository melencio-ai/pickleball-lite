<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Venue;
use App\Services\OpenPlay;
use App\Services\VenueTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_tokens_preserve_primary_and_accessible_contrast(): void
    {
        foreach (['#155542', '#5b21b6', '#facc15', '#ffffff', '#000000', '#777777', '#ff0000', '#00ff00', '#0000ff'] as $color) {
            $tokens = VenueTheme::tokens($color);
            $this->assertSame($color, $tokens['brand-primary']);
            $this->assertGreaterThanOrEqual(4.5, VenueTheme::contrast($tokens['brand-primary'], $tokens['brand-on-primary']));
            $this->assertGreaterThanOrEqual(4.5, VenueTheme::contrast($tokens['brand-hover'], $tokens['brand-on-primary']));
            $this->assertGreaterThanOrEqual(4.5, VenueTheme::contrast($tokens['brand-ink'], $tokens['brand-soft']));
        }
        $this->assertSame(VenueTheme::DEFAULT, VenueTheme::tokens(null)['brand-primary']);
        $this->assertSame(VenueTheme::DEFAULT, VenueTheme::tokens('red; --brand-primary: black')['brand-primary']);
        $this->assertSame('#5b21b6', VenueTheme::tokens('#5B21B6')['brand-primary']);
    }

    public function test_saved_theme_applies_to_public_and_staff_pages_and_checkout(): void
    {
        $this->travelTo(now()->setDate(2030, 1, 10)->setTime(0, 0));
        $this->seed();
        $venue = Venue::firstOrFail();
        $owner = $venue->users()->create(['name'=>'Owner','email'=>'theme@example.test','password'=>'sample-password','role'=>'owner']);
        $this->actingAs($owner);
        $this->patch(route('settings.branding'), ['brand_color' => '#5B21B6'])->assertSessionHasNoErrors();
        $theme = '--brand-primary:#5b21b6;--brand-on-primary:#ffffff';
        foreach ([route('landing'), route('book'), route('book',['step'=>1]), route('open-play'),
            route('staff.calendar'), route('staff.open-play'), route('settings.website'), route('settings.customization')] as $url) {
            $this->get($url)->assertOk()->assertSee($theme, false);
        }

        $data = ['court_id'=>$venue->courts()->firstOrFail()->id,'date'=>'2030-01-11','start'=>'19:00',
            'duration'=>60,'quoted_total'=>40000,'customer_name'=>'Theme Player','customer_phone'=>'09170000000'];
        $this->post(route('book.store'), $data)->assertRedirect(route('book.confirmed'));
        $this->get(route('book.confirmed'))->assertOk()->assertSee($theme,false);

        $venue->update(['qr_payments_enabled'=>true,
            'payment_qr_base'=>'00020101021126320014PH.PPMI.TESTQR0110TESTVENUE15204479953036085802PH5910LITE COURT6006MANILA63041052',
            'payment_messenger_url'=>'https://m.me/litecourts']);
        $this->post(route('book.store'), array_replace($data, ['start'=>'20:00']))->assertRedirect();
        $booking = Booking::latest('id')->firstOrFail();
        $this->get(route('book.payment',$booking->payment_token))->assertOk()->assertSee($theme,false);
        $session = app(OpenPlay::class)->create($venue, [
            'name'=>'Theme Open Play','date'=>'2030-01-11','start'=>'15:00','end'=>'16:00',
            'court_ids'=>[$data['court_id']], 'capacity'=>8,'price'=>'200.00','skill_level'=>'all','repeat'=>'none',
        ])[0];
        $registration = app(OpenPlay::class)->register($venue, $session->id, [
            'customer_name'=>'Theme Player','customer_phone'=>'09170000000','quoted_total'=>20000,
        ],'online');
        $this->get(route('open-play.payment',$registration->payment_token))->assertOk()->assertSee($theme,false);

        $this->patch(route('settings.branding'), ['brand_color'=>'#FACC15'])->assertSessionHasNoErrors();
        $this->get(route('landing'))->assertOk()->assertSee('--brand-primary:#facc15;--brand-on-primary:#000000',false);
        $this->get(route('book.payment',$booking->payment_token))->assertOk()->assertSee('--brand-primary:#facc15;--brand-on-primary:#000000',false);
    }
}
