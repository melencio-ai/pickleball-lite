<?php

namespace Tests\Feature;

use App\Services\HelpLibrary;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['pickle.service_role'=>'help','session.driver'=>'database','cache.default'=>'array']);
    }

    private function help(string $path='/'): string { return 'http://'.config('pickle.help_host').$path; }

    public function test_help_serves_all_guides_without_database_or_session(): void
    {
        DB::shouldReceive('connection')->never();
        $this->get($this->help())->assertOk()->assertSee('How can we help you?')->assertSee('50 guides')->assertHeaderMissing('Set-Cookie');
        $articles=HelpLibrary::articles();
        $this->assertCount(50,$articles);
        $this->assertCount(9,HelpLibrary::categories());
        foreach(HelpLibrary::categories() as $key=>$group) {
            $this->get($this->help('/category/'.$key))->assertOk()->assertSee($group['title'])->assertHeaderMissing('Set-Cookie');
        }
        foreach($articles as $article) {
            $this->get($this->help($article['path']))->assertOk()->assertSee($article['title'])->assertSee('On this page')->assertHeaderMissing('Set-Cookie')
                ->assertDontSee('₱5,000')->assertDontSee('50% off')->assertDontSee('15-day extension')->assertDontSee('75 days');
        }
        $this->artisan('help:check')->assertSuccessful();
    }

    public function test_search_seo_mobile_messenger_and_unknown_routes(): void
    {
        DB::shouldReceive('connection')->never();
        $this->get($this->help('/search?q=merchant+QR'))->assertOk()->assertSee('Configure your merchant QR code')->assertHeader('X-Robots-Tag','noindex, follow');
        $this->get($this->help('/search?q=zzzznotaguide'))->assertOk()->assertSee('No matching guides');
        $this->get($this->help('/search?q[]=bad'))->assertOk()->assertSee('Find an article');
        $this->get($this->help('/sitemap.xml'))->assertOk()->assertSee('https://'.config('pickle.help_host').'/articles/court-bookings/calendar')->assertSee('https://'.config('pickle.help_host').'/category/rewards');
        $this->get($this->help('/robots.txt'))->assertOk()->assertSee('Sitemap: https://'.config('pickle.help_host').'/sitemap.xml');
        $this->get($this->help())->assertOk()->assertSee('https://www.facebook.com/messages/t/pickleliteapp');
        $this->withHeader('User-Agent','Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')->get($this->help())->assertOk()->assertSee('href="https://m.me/pickleliteapp"',false);
        $this->get($this->help('/articles/payments/does-not-exist'))->assertNotFound()->assertSee('Article not found');
        $this->get($this->help('/admin'))->assertNotFound();
        $this->get($this->help('/login'))->assertNotFound();
        $this->post($this->help('/bookings'),[])->assertNotFound();
        $this->get('http://'.config('pickle.app_host').'/login')->assertNotFound();
        $this->get('http://'.config('pickle.book_host').'/')->assertNotFound();
        $this->get('http://'.config('pickle.product_host'))->assertNotFound();
        $this->get('http://internal.test/up')->assertOk();
    }

    public function test_other_service_roles_do_not_expose_help_host(): void
    {
        foreach(['management','booking','marketing'] as $role) {
            config(['pickle.service_role'=>$role]);
            $this->get($this->help())->assertNotFound();
        }
    }

    public function test_help_link_is_opt_in_and_uses_configured_origin(): void
    {
        config(['pickle.help_url'=>null]);
        $this->assertNull(HelpLibrary::appLink());
        config(['pickle.help_url'=>'javascript:alert(1)']);
        $this->assertNull(HelpLibrary::appLink());
        config(['pickle.help_url'=>'https://help.picklelite.cloud/']);
        $this->assertSame('https://help.picklelite.cloud/articles/payments/configure-qr',HelpLibrary::appLink('/articles/payments/configure-qr'));
    }
}
