(() => {
    const header = document.querySelector('.venue-site-header');
    if (!header) return;
    const update = () => {
        document.documentElement.style.setProperty('--venue-header-height', Math.ceil(header.getBoundingClientRect().height) + 'px');
    };
    update();
    if ('ResizeObserver' in window) new ResizeObserver(update).observe(header);
    else window.addEventListener('resize', update, {passive: true});
})();
