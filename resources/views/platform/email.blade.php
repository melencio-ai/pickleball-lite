@extends('layout')
@section('title','Email service')
@section('content')
<header class="section-heading page-heading"><div><h1>Email service</h1><p class="muted">Platform-wide SMTP configuration</p></div><span class="status-chip">{{ $state['enabled'] ? 'Enabled' : ($state['environment_fallback'] ? 'Environment SMTP' : 'Disabled') }}</span></header>
@if(session('email_rate_limit'))
    <p class="notice error" role="alert" data-email-rate-limit="{{ session('email_rate_limit.action') }}" data-retry-seconds="{{ session('email_rate_limit.seconds') }}">Too many email service requests. Try again in <span data-retry-countdown>{{ session('email_rate_limit.seconds') }}</span> seconds.</p>
@endif
<div class="tenant-stats">
    <div class="panel"><span class="muted">Submitted today</span><strong>{{ $counts['submitted'] ?? 0 }}</strong></div>
    <div class="panel"><span class="muted">Failed today</span><strong>{{ $counts['failed'] ?? 0 }}</strong></div>
    <div class="panel"><span class="muted">Sender</span><strong>Gmail</strong></div>
</div>
<div class="settings-grid">
    <section class="panel form-stack">
        <h2>SMTP configuration</h2>
        <p class="muted">The dedicated PickleLite Gmail sender uses implicit TLS. Credentials are encrypted and never displayed.</p>
        <div class="tenant-form-grid">
            <label>SMTP host<input readonly value="smtp.gmail.com"></label>
            <label>Port / encryption<input readonly value="465 / TLS"></label>
            <label>Sender name<input readonly value="PickleLite App"></label>
            <label>Sender email / username<input readonly value="picklelite.cloud@gmail.com"></label>
        </div>
        <form class="form-stack" method="post" action="{{ route('app.admin.email.save') }}" data-app-form data-email-action="save">@csrf @method('PATCH')
            <label>Google app password<input name="password" type="password" autocomplete="new-password" maxlength="64" required placeholder="{{ $state['configured'] ? 'Password saved — enter only to replace' : '16-character app password' }}"></label>
            <p class="muted">Use a Google app password, not your normal Gmail password. Replacing the password disables managed SMTP until retested.</p>
            <button data-app-submit>Save credentials</button>
        </form>
    </section>
    <section class="panel form-stack">
        <h2>Test and activation</h2>
        <p class="muted">Test emails are sent to your signed-in email address: {{ auth()->user()->email }}.</p>
        @if($state['configured'])
            <form method="post" action="{{ route('app.admin.email.test') }}" data-app-form data-email-action="test">@csrf<button class="secondary" data-app-submit>Send test email</button></form>
            @if($state['tested_at'])<p class="notice success">Last successful SMTP submission: {{ $state['tested_at'] }}.</p>@else<p class="notice">Not tested yet.</p>@endif
            <form class="form-stack" method="post" action="{{ route('app.admin.email.activation') }}" data-app-form data-email-action="activation">@csrf @method('PATCH')
                <label>Service<select name="enabled"><option value="0" @selected(!$state['enabled'])>Disabled</option><option value="1" @selected($state['enabled'])>Enabled</option></select></label>
                <label class="checkbox"><input type="checkbox" name="received" value="1"> I received the current test email.</label>
                <label class="checkbox"><input type="checkbox" name="registration" value="1" @checked($state['registration'])> Enable public registration</label>
                <label class="checkbox"><input type="checkbox" name="recovery" value="1" @checked($state['recovery'])> Enable password recovery</label>
                <p class="muted">Registration also requires all legal policies to be published. A disabled managed service never falls back to another mailer.</p>
                <button data-app-submit>Save activation</button>
            </form>
        @else
            <p>Save the app password to test and activate SMTP.</p>
        @endif
        <p class="muted">SMTP submission does not prove inbox delivery. Railway must allow outbound SMTP.</p>
    </section>
</div>
<section class="panel tenant-panel"><h2>Email submission history</h2><p class="muted">For privacy, recipients, message bodies, credentials and reset or verification links are not stored. A failed submission may have an uncertain delivery outcome.</p>
    <div class="tenant-table-scroll"><table class="tenant-table"><thead><tr><th>Time</th><th>Type</th><th>Status</th></tr></thead><tbody>
    @forelse($logs as $log)<tr><td>{{ \Carbon\CarbonImmutable::parse($log->created_at)->setTimezone('Asia/Manila')->format('M j, Y H:i') }}</td><td>{{ ucfirst(str_replace('_',' ',$log->kind)) }}</td><td>{{ ucfirst($log->status) }}</td></tr>@empty<tr><td colspan="3">No email submissions recorded.</td></tr>@endforelse
    </tbody></table></div>{{ $logs->links() }}
</section>
@endsection
