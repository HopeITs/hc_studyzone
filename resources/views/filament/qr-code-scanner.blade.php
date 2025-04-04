<div>
    <div id="reader" class="w-full" style="max-width: 600px;"></div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Html5Qrcode === 'undefined') {
                console.error('html5-qrcode library not loaded. Ensure it is properly included.');
                return;
            }

            const html5QrCode = new Html5Qrcode("reader");

            async function startScanner() {
                const readerElement = document.getElementById('reader');
                if (!readerElement) {
                    console.error('Reader element not found in DOM');
                    return;
                }

                try {
                    await html5QrCode.start(
                        { facingMode: "environment" },
                        { fps: 10, qrbox: { width: 250, height: 250 } },
                        (decodedText) => {
                            window.location.href = decodedText;
                            stopScanner();
                        },
                        (errorMessage) => {
                            console.warn(`QR Code scan error: ${errorMessage}`);
                        }
                    );
                } catch (err) {
                    console.error('Failed to start QR scanner:', err);
                }
            }

            function stopScanner() {
                html5QrCode.stop().catch(err => console.error('Failed to stop QR scanner:', err));
            }

            // Start the scanner immediately when the modal loads
            startScanner();

            // Stop the scanner when the modal is closed
            document.addEventListener('livewire:load', () => {
                Livewire.on('modal-closed', stopScanner);
            });

            // Stop the scanner when the page unloads
            window.addEventListener('beforeunload', stopScanner);
        });
    </script>
</div>
