(() => {
    'use strict';
    const frame = document.querySelector('[data-qr-payload]');
    if (frame && typeof qrcode === 'function') {
        try {
            const qr = qrcode(0, 'M');
            qr.addData(frame.dataset.qrPayload, 'Byte');
            qr.make();
            frame.innerHTML = qr.createSvgTag({cellSize: 5, margin: 20, scalable: true});
            const svg = frame.querySelector('svg');
            svg.setAttribute('role', 'img');
            svg.setAttribute('aria-label', 'Mã VietQR thanh toán đơn ' + frame.dataset.orderCode);
            const download = document.querySelector('[data-download-qr]');
            download.hidden = false;
            download.addEventListener('click', () => {
                // A crisp PNG can be opened by mobile banking gallery scanners.
                const count = qr.getModuleCount();
                const scale = 8;
                const quiet = 4;
                const canvas = document.createElement('canvas');
                canvas.width = canvas.height = (count + quiet * 2) * scale;
                const context = canvas.getContext('2d');
                context.fillStyle = '#fff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.fillStyle = '#000';
                for (let row = 0; row < count; row++) {
                    for (let column = 0; column < count; column++) {
                        if (qr.isDark(row, column)) context.fillRect((column + quiet) * scale, (row + quiet) * scale, scale, scale);
                    }
                }
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = 'VietQR-' + frame.dataset.orderCode + '.png';
                link.click();
            });
        } catch (_) {
            frame.textContent = 'Chưa thể hiển thị QR. Bạn có thể chuyển khoản bằng thông tin bên cạnh.';
        }
    }
    document.querySelectorAll('[data-copy-value]').forEach(button => {
        button.addEventListener('click', async () => {
            const feedback = document.querySelector('[data-copy-feedback]');
            try {
                await navigator.clipboard.writeText(button.dataset.copyValue);
                feedback.textContent = 'Đã sao chép.';
            } catch (_) {
                feedback.textContent = 'Hãy chọn và sao chép thông tin chuyển khoản trên màn hình.';
            }
        });
    });
})();
