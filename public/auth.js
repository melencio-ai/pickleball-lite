(() => {
    for (const toggle of document.querySelectorAll('[data-password-toggle]')) {
        const input = document.getElementById(toggle.dataset.passwordToggle);
        if (!input) continue;
        toggle.hidden = false;
        const label = toggle.getAttribute('aria-label') || 'Show password';
        const setVisible = (visible) => {
            input.type = visible ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(visible));
            toggle.setAttribute('aria-label', visible ? label.replace(/^Show/, 'Hide') : label);
            toggle.querySelector('[data-eye]').hidden = visible;
            toggle.querySelector('[data-eye-off]').hidden = !visible;
        };
        toggle.addEventListener('click', () => setVisible(input.type === 'password'));
        window.addEventListener('pageshow', () => setVisible(false));
    }
    for (const form of document.querySelectorAll('[data-auth-form]')) {
        const button = form.querySelector('[data-auth-submit]');
        const label = button?.querySelector('[data-submit-label]');
        if (!button || !label) continue;
        const original = label.textContent;
        let submitting = false;
        form.addEventListener('submit', (event) => {
            if (submitting) { event.preventDefault(); return; }
            submitting = true;
            form.setAttribute('aria-busy', 'true');
            button.disabled = true;
            label.textContent = button.dataset.busyText || 'Please wait…';
        });
        window.addEventListener('pageshow', () => {
            submitting = false;
            form.removeAttribute('aria-busy');
            button.disabled = false;
            label.textContent = original;
        });
    }
})();
