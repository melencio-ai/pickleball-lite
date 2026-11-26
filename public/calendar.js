(() => {
    const dialog = document.querySelector('#calendar-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const content = dialog.querySelector('[data-calendar-modal-content]');
    const close = dialog.querySelector('[data-calendar-close]');
    const toast = document.querySelector('[data-calendar-toast]');
    let modalRequest, refreshRequest, opener, saving = false, lastUrl, refreshPending = false;
    const grid = () => document.querySelector('[data-calendar-grid]');

    function announce(message, error = false) {
        toast.textContent = message;
        toast.classList.toggle('error', error);
        toast.hidden = false;
    }

    function showError(message) {
        const box = content.querySelector('[data-calendar-errors]');
        if (box) {
            box.textContent = message;
            box.hidden = false;
            box.scrollIntoView({block: 'nearest'});
        } else announce(message, true);
    }

    function updateRecurrence() {
        const repeat = content.querySelector('[data-calendar-repeat]');
        const count = content.querySelector('[data-calendar-occurrences]');
        if (!repeat || !count) return;
        count.hidden = repeat.value === 'none';
        count.querySelector('input').disabled = count.hidden;
    }

    async function openModal(url, trigger) {
        if (saving) return;
        if (!dialog.open) {
            opener = trigger || document.querySelector('[data-calendar-key="new"]');
            dialog.showModal();
        }
        lastUrl = url;
        modalRequest?.abort();
        const request = new AbortController();
        modalRequest = request;
        content.innerHTML = '<h2 id="calendar-modal-title">Calendar details</h2><div class="calendar-skeleton" aria-label="Loading details" role="status"><span></span><span></span><span></span><span></span></div>';
        content.setAttribute('aria-busy', 'true');
        close.focus();
        try {
            const response = await fetch(url, {headers: {'X-Calendar-Fragment': '1'}, signal: request.signal, cache: 'no-store'});
            if (!response.ok || response.redirected) throw new Error('Unable to load the details. Please refresh or sign in again.');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fragment = doc.querySelector('[data-calendar-fragment]');
            if (!fragment) throw new Error('Unable to load the details. Please refresh or sign in again.');
            if (request.signal.aborted || !dialog.open) return;
            content.replaceChildren(fragment);
            updateRecurrence();
            updateCountdowns();
            content.querySelector('h2')?.setAttribute('tabindex', '-1');
            content.querySelector('h2')?.focus({preventScroll: true});
        } catch (error) {
            if (error.name === 'AbortError') return;
            content.innerHTML = '<h2 id="calendar-modal-title">Unable to load details</h2><p role="alert"></p><button type="button" data-calendar-retry>Retry</button>';
            content.querySelector('p').textContent = error.message;
        } finally {
            if (modalRequest === request) content.removeAttribute('aria-busy');
        }
    }

    async function refreshGrid() {
        const previous = grid();
        if (!previous || refreshRequest) return;
        const request = new AbortController();
        refreshRequest = request;
        const scroll = previous.querySelector('[data-calendar-scroll]');
        const left = scroll?.scrollLeft || 0;
        const top = scroll?.scrollTop || 0;
        const key = document.activeElement?.dataset.calendarKey;
        const historyOpen = previous.querySelector('.calendar-history')?.open;
        previous.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(previous.dataset.calendarUrl, {signal: request.signal, cache: 'no-store'});
            if (!response.ok || response.redirected) throw new Error('Calendar refresh failed. Use Refresh or reload to see current availability.');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = doc.querySelector('[data-calendar-grid]');
            if (!next) throw new Error('Calendar refresh failed. Sign in again if needed.');
            previous.replaceWith(next);
            const nextScroll = next.querySelector('[data-calendar-scroll]');
            if (nextScroll) { nextScroll.scrollLeft = left; nextScroll.scrollTop = top; }
            if (historyOpen && next.querySelector('.calendar-history')) next.querySelector('.calendar-history').open = true;
            if (!dialog.open && key) {
                const target = Array.from(next.querySelectorAll('[data-calendar-key]')).find(el => el.dataset.calendarKey === key);
                (target || document.querySelector('[data-calendar-key="new"]'))?.focus({preventScroll: true});
            }
        } finally {
            refreshRequest = null;
            previous.removeAttribute('aria-busy');
        }
    }

    function safeRefresh() {
        refreshGrid().catch(error => announce(error.message, true));
    }

    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-calendar-modal]');
        if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) {
            event.preventDefault();
            openModal(link.href, link);
        }
        if (event.target.closest('[data-calendar-refresh]')) safeRefresh();
        if (event.target.closest('[data-calendar-retry]')) openModal(lastUrl);
    });
    content.addEventListener('change', event => {
        if (event.target.matches('[data-calendar-repeat]')) updateRecurrence();
    });
    close.addEventListener('click', () => { if (!saving) dialog.close(); });
    dialog.addEventListener('cancel', event => { if (saving) event.preventDefault(); });
    dialog.addEventListener('close', () => {
        modalRequest?.abort();
        const key = opener?.dataset.calendarKey;
        const target = key && Array.from(document.querySelectorAll('[data-calendar-key]')).find(el => el.dataset.calendarKey === key);
        (target || document.querySelector('[data-calendar-key="new"]'))?.focus({preventScroll: true});
        if (refreshPending) { refreshPending = false; safeRefresh(); }
    });

    dialog.addEventListener('submit', async event => {
        const form = event.target.closest('form[data-calendar-form]');
        if (!form) return;
        event.preventDefault();
        if (saving) return;
        saving = true;
        close.disabled = true;
        const buttons = Array.from(content.querySelectorAll('button[type="submit"], button:not([type])'));
        const states = buttons.map(button => button.disabled);
        buttons.forEach(button => button.disabled = true);
        const errorBox = content.querySelector('[data-calendar-errors]');
        if (errorBox) errorBox.hidden = true;
        let success = false;
        try {
            const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, cache: 'no-store'});
            const result = await response.json().catch(() => ({}));
            if (!response.ok || response.redirected) {
                const errors = Object.values(result.errors || {}).flat().join(' ');
                showError(errors || result.message || 'Unable to save. Check your session and try again.');
                if (response.status === 422) refreshPending = true;
                return;
            }
            if (!result.message) throw new Error('The server response was unclear. Refresh the calendar before trying again.');
            success = true;
            announce(result.message);
            if (refreshRequest) {
                refreshRequest.abort();
                refreshRequest = null;
            }
            try { await refreshGrid(); }
            catch (error) { announce(result.message + ' ' + error.message, true); }
        } catch (error) {
            showError(error.message + ' Check the calendar before submitting again.');
        } finally {
            saving = false;
            close.disabled = false;
            buttons.forEach((button, i) => button.disabled = states[i]);
            if (success) {
                refreshPending = false;
                dialog.close();
            }
        }
    });

    function updateCountdowns() {
        const now = Math.floor(Date.now() / 1000);
        content.querySelectorAll('[data-calendar-countdown]').forEach(node => {
            const remaining = Math.max(0, Number(node.dataset.calendarCountdown) - now);
            node.textContent = Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0');
            if (!remaining) {
                node.textContent = 'Expired';
                const verify = content.querySelector('.calendar-verify');
                if (verify) verify.hidden = true;
            }
        });
        const expired = Array.from(grid()?.querySelectorAll('[data-hold-expires]') || []).some(node => Number(node.dataset.holdExpires) <= now);
        if (expired && !refreshRequest && !saving) {
            if (dialog.open) refreshPending = true;
            else safeRefresh();
        }
    }

    window.setInterval(updateCountdowns, 1000);
    window.setInterval(() => {
        if (document.visibilityState === 'visible' && !dialog.open && !saving) safeRefresh();
    }, 30000);
    if (dialog.dataset.initialUrl) openModal(dialog.dataset.initialUrl);
})();
