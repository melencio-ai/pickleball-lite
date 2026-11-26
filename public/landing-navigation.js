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

(() => {
    const dialog = document.querySelector('#gallery-dialog');
    const tiles = Array.from(document.querySelectorAll('[data-gallery-image]'));
    if (!dialog || !tiles.length || typeof dialog.showModal !== 'function') return;
    const image = dialog.querySelector('[data-gallery-large]');
    const caption = dialog.querySelector('[data-gallery-caption]');
    const count = dialog.querySelector('[data-gallery-count]');
    const close = dialog.querySelector('[data-gallery-close]');
    const prev = dialog.querySelector('[data-gallery-prev]');
    const next = dialog.querySelector('[data-gallery-next]');
    let index = 0;
    let opener = null;

    function show(indexToShow) {
        index = (indexToShow + tiles.length) % tiles.length;
        const tile = tiles[index];
        const label = tile.querySelector('img').alt;
        image.src = tile.href;
        image.alt = label;
        caption.textContent = label;
        count.textContent = (index + 1) + ' / ' + tiles.length;
        prev.disabled = next.disabled = tiles.length < 2;
    }

    tiles.forEach((tile, i) => tile.addEventListener('click', event => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        opener = tile;
        show(i);
        dialog.showModal();
        close.focus();
    }));
    close.addEventListener('click', () => dialog.close());
    prev.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    dialog.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            show(index + (event.key === 'ArrowLeft' ? -1 : 1));
        }
    });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right ||
            event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => opener?.focus({preventScroll:true}));
})();
