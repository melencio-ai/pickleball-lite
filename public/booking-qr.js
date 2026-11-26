const target = document.querySelector('[data-qr-url]');
const download = document.querySelector('[data-qr-download]');

if (target && download && typeof qrcode === 'function') {
    const qr = qrcode(0, 'M');
    qr.addData(target.dataset.qrUrl);
    qr.make();

    const svg = qr.createSvgTag(5, 20);
    target.innerHTML = svg;
    download.href = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
    download.hidden = false;
}
