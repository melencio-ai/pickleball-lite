<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_roles_restrict_hosts_before_accessing_routes(): void
    {
        $this->seed();
        $venue = Venue::where('legacy_slot', 1)->firstOrFail();
        $web = 'http://'.config('pickle.product_host');
        $app = 'http://'.config('pickle.app_host');
        $book = 'http://'.config('pickle.book_host');
        $legacy = 'http://'.config('pickle.legacy_host');

        config(['pickle.service_role' => 'marketing']);
        $this->get($web)->assertOk()->assertSee('More games. Less admin work.');
        $this->get($app.'/login')->assertNotFound();
        $this->get($book.'/'.$venue->slug)->assertNotFound();
        $this->get($legacy)->assertNotFound();
        $this->post($web.'/get-started', ['venue_name'=>'A venue','name'=>'Enquiry','email'=>'service@example.test'])
            ->assertRedirect($web.'#get-started');
        $this->assertDatabaseHas('product_enquiries', ['email'=>'service@example.test']);

        config(['pickle.service_role' => 'booking']);
        $this->get($book.'/'.$venue->slug)->assertOk()->assertSee($venue->name);
        $this->get($book.'/'.$venue->slug.'/book')->assertOk();
        $this->get($app.'/login')->assertNotFound();
        $this->get($web)->assertNotFound();
        $this->get($legacy)->assertNotFound();

        config(['pickle.service_role' => 'management']);
        $this->get($app.'/login')->assertOk();
        $this->get($legacy)->assertOk()->assertSee($venue->name);
        $this->get($book.'/'.$venue->slug)->assertNotFound();
        $this->get($web)->assertNotFound();
        $this->get('http://unrecognized.test/')->assertNotFound();

        config(['pickle.service_role' => 'invalid']);
        $this->get($app.'/login')->assertNotFound();
        $this->get($legacy)->assertNotFound();
        $this->get('http://internal.test/up')->assertOk();
    }

    public function test_readiness_gate_does_not_run_migrations_or_write_records(): void
    {
        $count = DB::table('migrations')->count();
        $this->artisan('platform:check-schema')->assertSuccessful();
        $this->assertSame($count, DB::table('migrations')->count());
        $this->assertDatabaseCount('marketing_sessions', 0);
        $this->assertDatabaseCount('booking_sessions', 0);
    }
}
