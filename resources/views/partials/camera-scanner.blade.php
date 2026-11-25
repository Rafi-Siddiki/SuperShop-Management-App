{{--
    Shared barcode input helpers for staff pages.

    USB / Bluetooth scanners act as keyboards: they type the code and press
    Enter, so a focused text input is all they need. For phones and laptops
    without a scanner, CameraScanner reads EAN/UPC/Code-128 from the camera
    (html5-qrcode, loaded on first use). Browsers only allow the camera on
    https:// or http://localhost / 127.0.0.1.
--}}
<script>
    window.ScanSound = (() => {
        let ctx;
        const beep = (freq, ms) => {
            try {
                ctx ??= new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator(), gain = ctx.createGain();
                osc.frequency.value = freq; osc.type = 'square'; gain.gain.value = 0.04;
                osc.connect(gain).connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime + ms / 1000);
            } catch (e) { /* audio not available */ }
        };
        return { ok: () => beep(1400, 80), error: () => { beep(300, 160); setTimeout(() => beep(250, 200), 180); } };
    })();

    window.CameraScanner = (() => {
        const SRC = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
        let loading, scanner, lastCode = '', lastAt = 0;

        const load = () => loading ??= new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = SRC; s.onload = resolve; s.onerror = () => reject(new Error('Could not load the camera scanner. Check the internet connection.'));
            document.head.appendChild(s);
        });

        return {
            /** Start scanning into the element with the given id; onCode(code) is called once per distinct read. */
            async start(elementId, onCode) {
                if (! window.isSecureContext) {
                    throw new Error('The camera only works over https:// or on localhost.');
                }
                await load();
                const F = Html5QrcodeSupportedFormats;
                scanner = new Html5Qrcode(elementId, {
                    formatsToSupport: [F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E, F.CODE_128, F.CODE_39, F.ITF],
                    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                    verbose: false,
                });
                await scanner.start(
                    { facingMode: 'environment' },
                    { fps: 12, qrbox: (w, h) => ({ width: Math.min(w * 0.9, 360), height: Math.min(h * 0.5, 160) }) },
                    (text) => {
                        // Cameras read the same code many times per second; accept it once per 2 s.
                        const now = Date.now();
                        if (text === lastCode && now - lastAt < 2000) return;
                        lastCode = text; lastAt = now;
                        onCode(text);
                    },
                    () => {}
                );
            },
            async stop() {
                if (scanner) {
                    try { await scanner.stop(); scanner.clear(); } catch (e) { /* already stopped */ }
                    scanner = null;
                }
            },
        };
    })();
</script>
