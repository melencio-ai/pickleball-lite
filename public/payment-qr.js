const hold = document.querySelector('[data-payment-hold]');

if (hold) {
    const countdown = hold.querySelector('[data-hold-countdown]');
    const expiredMessage = hold.querySelector('[data-hold-expired]');
    const label = hold.querySelector('#payment-hold-label');
    const paymentInstructions = document.querySelector('[data-payment-instructions]');
    const deadline = Date.now() + Number(hold.dataset.secondsLeft) * 1000;

    function updateCountdown() {
        const remaining = Math.max(0, deadline - Date.now());
        const seconds = Math.ceil(remaining / 1000);
        const minutesText = String(Math.floor(seconds / 60)).padStart(2, '0');
        const secondsText = String(seconds % 60).padStart(2, '0');

        countdown.textContent = `${minutesText}:${secondsText}`;
        if (remaining === 0) {
            label.hidden = true;
            countdown.hidden = true;
            expiredMessage.hidden = false;
            paymentInstructions.hidden = true;
            window.location.reload();
            return;
        }

        window.setTimeout(updateCountdown, remaining % 1000 || 1000);
    }

    updateCountdown();
}

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
}
