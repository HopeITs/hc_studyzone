{{-- <div>
    <div id="reader" class="w-full" style="max-width: 600px;"></div>

    <script>
        document.addEventListener('livewire:load', function () {
            if (typeof Html5Qrcode === 'undefined') {
                console.error('html5-qrcode library not loaded.');
                return;
            }

            const html5QrCode = new Html5Qrcode("reader");

            const startScanner = async () => {
                try {
                    await html5QrCode.start(
                        { facingMode: "environment" },
                        { fps: 10, qrbox: { width: 250, height: 250 } },
                        decodedText => {
                            window.location.href = decodedText;
                            html5QrCode.stop();
                        },
                        errorMessage => {
                            console.warn(`QR error: ${errorMessage}`);
                        }
                    );
                } catch (err) {
                    console.error('Scanner start failed:', err);
                }
            };

            const stopScanner = () => {
                html5QrCode.stop().catch(err => console.error('Stop failed:', err));
            };

            const observer = new MutationObserver(() => {
                if (document.getElementById('reader')?.offsetParent !== null) {
                    startScanner();
                    observer.disconnect();
                }
            });

            observer.observe(document.body, { childList: true, subtree: true });

            window.addEventListener('beforeunload', stopScanner);
            Livewire.on('modal-closed', stopScanner);
        });
    </script>
</div> --}}


{{--             ALPINE.JS                --}}
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
                        window.location.href = `/student/${qrCodeMessage}/entry`;
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
