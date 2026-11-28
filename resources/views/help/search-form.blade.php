<form action="/search" method="get" class="help-search" role="search" data-help-search>
    <label class="help-visually-hidden" for="help-search-input">Search guides</label>
    <div class="help-search-row"><svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input id="help-search-input" name="q" type="search" maxlength="120" value="{{ $query ?? '' }}" placeholder="Search articles and guides..." autocomplete="off"><button type="submit">Search</button></div>
    <div class="help-suggestions" data-help-suggestions hidden><div data-help-suggestion-list></div><p data-help-search-status role="status" class="help-visually-hidden"></p></div>
</form>
