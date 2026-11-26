// Server-rendered steps work without JavaScript. Guard the final submit against double taps.
const form = document.querySelector('[data-booking-form]');
const submit = form?.querySelector('[data-booking-submit]');
if (form && submit) {
    form.addEventListener('submit', event => {
        if (submit.disabled) { event.preventDefault(); return; }
        submit.disabled = true;
    });
    window.addEventListener('pageshow', () => { submit.disabled = false; });
}
