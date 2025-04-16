<div
    x-data="{
        html5QrCode: null,
        startScanner() {
            this.html5QrCode = new Html5Qrcode('scanner');
            Html5Qrcode.getCameras().then(devices => {
                if (devices.length) {
                    const cameraId = devices[0].id;
                    this.html5QrCode.start(cameraId, {
                        fps: 10,
                        qrbox: 250
                    },
                    qrCodeMessage => {
                        this.html5QrCode.stop();

                        window.location.href = `${qrCodeMessage}`;
                    },
                    errorMessage => {
                        console.warn(errorMessage);
                    });
                }
            }).catch(err => console.error('Camera init error', err));
        },
        stopScanner() {
            if (this.html5QrCode) {
                this.html5QrCode.stop().catch(err => console.error('Stop failed', err));
            }
        }
    }"
    x-init="startScanner();
        window.addEventListener('close', stopScanner);"
>
    <div id="scanner" class="w-full h-64 bg-gray-100"></div>
</div>
