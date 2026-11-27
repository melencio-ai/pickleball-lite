<?php

use App\Models\Venue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('venue:create-owner {email} {--name=Venue Owner} {--venue=}', function () {
    $slug = $this->option('venue');
    $venue = $slug ? Venue::where('slug',$slug)->first() : Venue::where('legacy_slot',1)->first();
    if (! $venue) {
        $this->error('Run php artisan db:seed to initialize the venue first.');

        return 1;
    }

    $email = strtolower($this->argument('email'));
    $name = $this->option('name');
    $password = $this->secret('Owner password (at least 12 characters)');
    $validator = Validator::make(compact('email', 'name', 'password'), [
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'name' => ['required', 'string', 'max:100'],
        'password' => ['required', 'string', 'min:12', 'max:72'],
    ]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }

    $venue->users()->create($validator->validated() + ['role' => 'owner']);
    $this->info('Venue owner created.');

    return 0;
})->purpose('Create an owner account for the venue');


Artisan::command('platform:grant-admin {email}', function () {
    $user = \App\Models\User::whereRaw('LOWER(email) = ?', [strtolower($this->argument('email'))])->first();
    if (!$user) {
        $this->error('No existing account found. Create the verified operator account first.');
        return 1;
    }
    if (!$this->confirm('Grant platform administration to '.$user->email.'?')) return 1;
    $user->forceFill(['platform_admin'=>true])->save();
    $this->info('Platform administration granted.');
    return 0;
})->purpose('Grant explicit platform administration to an existing account');


Artisan::command('platform:check-schema', function () {
    // A read-only readiness gate for services that must not run migrations.
    $schema = \Illuminate\Support\Facades\Schema::class;
    foreach (['venues', 'venue_memberships', 'venue_subscriptions', 'product_enquiries',
        'marketing_sessions', 'booking_sessions', 'bookings', 'open_play_sessions', 'legal_documents', 'legal_document_versions', 'legal_acceptances', 'privacy_requests', 'venue_onboarding', 'trial_extensions', 'venue_notifications', 'notification_preferences', 'migrations'] as $table) {
        if (!$schema::hasTable($table)) {
            $this->error('Required schema missing: '.$table.'. Run migrations on the management service first.');
            return 1;
        }
    }
    if (!$schema::hasColumns('venues', ['slug','published','legacy_slot']) ||
        !$schema::hasColumn('users', 'platform_admin') || !$schema::hasColumn('open_play_registrations','cancellation_policy')) {
        $this->error('Tenant schema is incomplete.');
        return 1;
    }
    $required = ['2026_11_27_000000_add_venue_tenancy','2026_11_27_000100_add_platform_operations',
        '2026_11_27_000200_add_public_service_sessions','2026_11_27_000300_add_legal_policies','2026_11_27_000400_add_self_service_platform',
        '2026_11_27_000500_remove_setup_fee','2026_11_27_000600_add_platform_email_settings','2026_11_27_000700_add_platform_rewards'];
    if (\Illuminate\Support\Facades\DB::table('migrations')->whereIn('migration',$required)->count() !== count($required)) {
        $this->error('Management migrations have not completed.');
        return 1;
    }
    $this->info('Shared platform schema is ready.');
    return 0;
})->purpose('Verify the shared schema without mutating the database');


Artisan::command('platform:notifications', function () {
    \App\Services\VenueNotifications::sweep();
    $this->info('Trial and subscription reminders reconciled.');
})->purpose('Reconcile trial expiry and deduplicated subscription reminders');

\Illuminate\Support\Facades\Schedule::command('platform:notifications')->everyFiveMinutes()->withoutOverlapping(10);


Artisan::command('platform:prune-email-logs', function () {
    $deleted = \Illuminate\Support\Facades\DB::table('platform_email_logs')->where('created_at','<',now()->subDays(90))->delete();
    $this->info('Pruned '.$deleted.' expired email log records.');
})->purpose('Remove email submission metadata older than 90 days');

\Illuminate\Support\Facades\Schedule::command('platform:prune-email-logs')->daily()->withoutOverlapping(10);


Artisan::command('platform:rewards',function() {
    \App\Services\PlatformRewards::sweep();
    $this->info('Reward reminders, expirations and activity retention reconciled.');
})->purpose('Reconcile platform reward-covered terms and streak reminders');
\Illuminate\Support\Facades\Schedule::command('platform:rewards')->hourly()->withoutOverlapping(10);
