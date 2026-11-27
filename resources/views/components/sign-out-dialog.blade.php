<dialog id="sign-out-confirmation" class="sign-out-dialog" aria-labelledby="sign-out-title" aria-describedby="sign-out-description" data-sign-out-dialog>
    <form method="post" action="{{ \App\Services\VenueContext::url('logout') }}" class="sign-out-form" data-sign-out-form>
        @csrf
        <div class="sign-out-heading">
            <span class="sign-out-icon"><x-workspace-icon name="logout"/></span>
            <div><h2 id="sign-out-title">Sign out?</h2><p id="sign-out-description">Are you sure you want to sign out of Pickle Lite?</p></div>
        </div>
        <div class="sign-out-actions">
            <button type="button" class="sign-out-cancel" autofocus data-sign-out-cancel>Cancel</button>
            <button type="submit" class="sign-out-confirm" data-sign-out-confirm>Sign out</button>
        </div>
    </form>
</dialog>
<noscript>
    <details class="sign-out-fallback"><summary>Sign out</summary>
        <p>Are you sure you want to sign out of Pickle Lite?</p>
        <form method="post" action="{{ \App\Services\VenueContext::url('logout') }}">@csrf<button type="submit">Confirm sign out</button></form>
    </details>
    <style>[data-sign-out-open]{display:none!important}</style>
</noscript>
