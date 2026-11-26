const canvas = document.querySelector('[data-payment-qr]');
const download = document.querySelector('[data-payment-download]');

if (canvas && download && typeof qrcode === 'function') {
    qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];

    const qr = qrcode(0, 'M');
    qr.addData(canvas.dataset.paymentQr);
    qr.make();

    const modules = qr.getModuleCount();
    const moduleSize = 6;
    const quietZone = 4;
    const side = (modules + 2 * quietZone) * moduleSize;
    canvas.width = side;
    canvas.height = side;
    const context = canvas.getContext('2d');

    context.fillStyle = '#fff';
    context.fillRect(0, 0, side, side);
    context.fillStyle = '#000';
    for (let row = 0; row < modules; row++) {
        for (let column = 0; column < modules; column++) {
            if (qr.isDark(row, column)) {
                context.fillRect((column + quietZone) * moduleSize, (row + quietZone) * moduleSize, moduleSize, moduleSize);
            }
        }
    }

    download.href = canvas.toDataURL('image/png');
    download.hidden = false;

    window.setTimeout(() => window.location.reload(), (Number(canvas.dataset.secondsLeft) + 1) * 1000);
}
