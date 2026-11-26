const filters = document.querySelector('[data-booking-filters]');
const feedback = document.querySelector('[data-booking-feedback]');
let results = document.querySelector('[data-booking-results]');
const detailsSelector = '[data-booking-form]';
let request;
const draft = {};

function fillDetails() {
    const form = results.querySelector(detailsSelector);
    if (!form) return;

    for (const [name, value] of Object.entries(draft)) {
        form.elements[name].value = value;
    }
}

async function loadResults(url, openDetails) {
    request?.abort();
    const controller = new AbortController();
    request = controller;
    results.setAttribute('aria-busy', 'true');
    feedback.textContent = 'Updating court times…';

    try {
        const response = await fetch(url, { signal: controller.signal, cache: 'no-store' });
        if (!response.ok) throw new Error('Could not load court times');
        const html = new DOMParser().parseFromString(await response.text(), 'text/html');
        const next = html.querySelector('[data-booking-results]');
        if (!next) throw new Error('Court times missing');
        if (controller.signal.aborted) return;
        results.replaceWith(next);
        results = next;
        history.replaceState(null, '', url);
        fillDetails();
        feedback.textContent = openDetails && results.querySelector(detailsSelector)
            ? 'Booking details ready.'
            : 'Court times updated.';

        if (openDetails) {
            const focusTarget = results.querySelector('#booking-form') || results.querySelector('#available-title');
            focusTarget.focus({ preventScroll: true });
            focusTarget.scrollIntoView({ block: 'nearest' });
        }
    } catch (error) {
        if (error.name !== 'AbortError') window.location.assign(url);
    } finally {
        if (request === controller) results.removeAttribute('aria-busy');
    }
}

if (filters && results && feedback) {
    filters.querySelector('[data-filter-submit]').hidden = true;
    const initial = document.querySelector('[data-booking-draft]')?.content || results;
    for (const name of ['customer_name', 'customer_phone']) {
        const input = initial.querySelector(`[name="${name}"]`);
        if (input) draft[name] = input.value;
    }

    function updateFilters() {
        if (!filters.reportValidity()) return;
        const url = new URL(filters.action);
        url.search = new URLSearchParams(new FormData(filters)).toString();
        loadResults(url, false);
    }

    filters.addEventListener('change', updateFilters);
    filters.addEventListener('submit', event => {
        event.preventDefault();
        updateFilters();
    });

    document.addEventListener('click', event => {
        const slot = event.target.closest('[data-booking-slot]');
        if (!slot || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (results.getAttribute('aria-busy') === 'true') return;
        loadResults(new URL(slot.href), true);
    });

    document.addEventListener('input', event => {
        if (event.target.form?.matches(detailsSelector)) draft[event.target.name] = event.target.value;
    });

    document.addEventListener('submit', event => {
        if (event.target.matches(detailsSelector)) {
            if (results.getAttribute('aria-busy') === 'true') {
                event.preventDefault();
                return;
            }

            event.target.querySelector('[data-booking-submit]').disabled = true;
        }
    });
    window.addEventListener('pageshow', () => {
        const button = results.querySelector('[data-booking-submit]');
        if (button) button.disabled = false;
    });

    if (results.querySelector('#booking-form [role="alert"]')) results.querySelector('#booking-form').focus();
}
