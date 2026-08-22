const barcodePattern = /^\d{8,14}$/;
const rearCameraPattern = /back|rear|environment|trasera|trás|arrière|posteriore|rück|后置|後置/i;
const frontCameraPattern = /front|user|facetime|integrated|webcam|frontal/i;

document.addEventListener('DOMContentLoaded', () => {
    const openButton = document.querySelector('[data-barcode-scanner-open]');
    const modal = document.querySelector('[data-barcode-scanner-modal]');
    const barcodeInput = document.getElementById('barcode');

    if (!openButton || !modal || !barcodeInput) {
        return;
    }

    const video = modal.querySelector('[data-barcode-scanner-video]');
    const status = modal.querySelector('[data-barcode-scanner-status]');
    const error = modal.querySelector('[data-barcode-scanner-error]');
    const retryButton = modal.querySelector('[data-barcode-scanner-retry]');
    const cancelButtons = modal.querySelectorAll('[data-barcode-scanner-cancel]');
    let reader = null;
    let controls = null;
    let starting = false;
    let completed = false;
    let readyToScan = false;

    const loadReader = async () => {
        if (reader) {
            return;
        }

        const { BarcodeFormat, BrowserMultiFormatReader } = await import('@zxing/browser');
        reader = new BrowserMultiFormatReader();
        reader.possibleFormats = [
            BarcodeFormat.EAN_8,
            BarcodeFormat.EAN_13,
            BarcodeFormat.UPC_A,
            BarcodeFormat.UPC_E,
            BarcodeFormat.ITF,
            BarcodeFormat.CODE_128,
        ];
    };

    const stopCamera = () => {
        controls?.stop();
        controls = null;

        if (video.srcObject instanceof MediaStream) {
            video.srcObject.getTracks().forEach((track) => track.stop());
            video.srcObject = null;
        }
    };

    const closeScanner = (restoreFocus = true) => {
        stopCamera();
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        starting = false;

        if (restoreFocus) {
            openButton.focus();
        }
    };

    const showError = (message) => {
        status.textContent = '';
        error.textContent = message;
        error.classList.remove('hidden');
        retryButton.classList.remove('hidden');
    };

    const decode = async (constraints) => reader.decodeFromConstraints(constraints, video, (result) => {
        if (!result || completed || !readyToScan) {
            return;
        }

        const value = result.getText();
        if (!barcodePattern.test(value)) {
            showError('Se detectó un código, pero no contiene entre 8 y 14 dígitos. Intente nuevamente.');
            return;
        }

        completed = true;
        barcodeInput.value = value;
        barcodeInput.dispatchEvent(new Event('input', { bubbles: true }));
        barcodeInput.dispatchEvent(new Event('change', { bubbles: true }));
        closeScanner(false);
        barcodeInput.focus();
    });

    const currentTrack = () => video.srcObject instanceof MediaStream
        ? video.srcObject.getVideoTracks()[0]
        : null;

    const rearCameraDevice = async (devices) => {
        const labelledRear = devices.find((device) => rearCameraPattern.test(device.label));
        if (labelledRear) {
            return labelledRear;
        }

        for (const device of devices) {
            let probe = null;
            try {
                probe = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: { deviceId: { exact: device.deviceId } },
                });
                const track = probe.getVideoTracks()[0];
                const settings = track?.getSettings?.() ?? {};
                const capabilities = track?.getCapabilities?.() ?? {};
                const facingModes = Array.isArray(capabilities.facingMode) ? capabilities.facingMode : [];

                if (settings.facingMode === 'environment' || facingModes.includes('environment')) {
                    return device;
                }
            } catch {
                // Una cámara no disponible no impide probar las restantes.
            } finally {
                probe?.getTracks().forEach((track) => track.stop());
            }
        }

        return null;
    };

    const ensureRearCamera = async () => {
        const track = currentTrack();
        const settings = track?.getSettings?.() ?? {};
        if (settings.facingMode === 'environment') {
            return;
        }

        const devices = (await navigator.mediaDevices.enumerateDevices())
            .filter((device) => device.kind === 'videoinput');
        const currentLabel = devices.find((device) => device.deviceId === settings.deviceId)?.label ?? '';
        const knownFrontCamera = settings.facingMode === 'user' || frontCameraPattern.test(currentLabel);

        if (!knownFrontCamera) {
            return;
        }

        const labelledRear = devices.find((device) => rearCameraPattern.test(device.label));

        if (labelledRear) {
            stopCamera();
            controls = await decode({
                audio: false,
                video: {
                    deviceId: { exact: labelledRear.deviceId },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
            });
            return;
        }

        stopCamera();
        const rearDevice = await rearCameraDevice(devices.filter((device) => device.deviceId !== settings.deviceId));

        if (rearDevice) {
            controls = await decode({
                audio: false,
                video: {
                    deviceId: { exact: rearDevice.deviceId },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
            });
            return;
        }

        throw new Error('rear-camera-unavailable');
    };

    const cameraErrorMessage = (cameraError) => {
        if (!window.isSecureContext) {
            return 'La cámara requiere una conexión segura (HTTPS) para acceder desde otro dispositivo.';
        }
        if (cameraError?.message === 'rear-camera-unavailable') {
            return 'No se encontró una cámara trasera disponible para escanear productos.';
        }
        if (cameraError?.name === 'NotAllowedError' || cameraError?.name === 'SecurityError') {
            return 'No se concedió permiso para utilizar la cámara. Puede habilitarlo en el navegador y reintentar.';
        }
        if (cameraError?.name === 'NotFoundError' || cameraError?.name === 'OverconstrainedError') {
            return 'No se encontró una cámara compatible en este dispositivo.';
        }
        if (cameraError?.name === 'NotReadableError' || cameraError?.name === 'AbortError') {
            return 'La cámara no está disponible o está siendo utilizada por otra aplicación.';
        }

        return 'No fue posible iniciar el escáner. Verifique el acceso a la cámara e intente nuevamente.';
    };

    const startScanner = async () => {
        if (starting) {
            return;
        }

        error.classList.add('hidden');
        retryButton.classList.add('hidden');
        status.textContent = 'Solicitando acceso a la cámara…';
        completed = false;
        readyToScan = false;

        if (!navigator.mediaDevices?.getUserMedia) {
            showError('Este navegador no permite utilizar la cámara para escanear códigos.');
            return;
        }

        starting = true;
        stopCamera();

        try {
            await loadReader();
            controls = await decode({
                audio: false,
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
            });
            await ensureRearCamera();
            readyToScan = true;
            status.textContent = 'Coloque el código de barras dentro del recuadro.';
        } catch (cameraError) {
            stopCamera();
            showError(cameraErrorMessage(cameraError));
        } finally {
            starting = false;
        }
    };

    openButton.addEventListener('click', () => {
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        startScanner();
    });
    retryButton.addEventListener('click', startScanner);
    cancelButtons.forEach((button) => button.addEventListener('click', () => closeScanner()));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeScanner();
        }
    });
    window.addEventListener('pagehide', () => closeScanner(false));
});
