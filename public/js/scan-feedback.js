(function () {
    const script = document.currentScript;
    const urls = { success: script.dataset.ok, error: script.dataset.ng };
    const buffers = new Map();
    const playbackGain = 2; // Approximately +6 dB before peak compression.
    let context = null;
    let outputGain = null;
    let activeSource = null;
    let latestRequest = 0;

    function getContext() {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!context && AudioContextClass) {
            context = new AudioContextClass();
            outputGain = context.createGain();
            outputGain.gain.value = playbackGain;

            const compressor = context.createDynamicsCompressor();
            compressor.threshold.value = -3;
            compressor.knee.value = 0;
            compressor.ratio.value = 20;
            compressor.attack.value = 0.003;
            compressor.release.value = 0.25;

            outputGain.connect(compressor);
            compressor.connect(context.destination);
        }
        return context;
    }

    function loadSound(type) {
        if (!buffers.has(type)) {
            const pending = fetch(urls[type])
                .then((response) => {
                    if (!response.ok) throw new Error('Audio unavailable');
                    return response.arrayBuffer();
                })
                .then((bytes) => context.decodeAudioData(bytes))
                .catch(() => {
                    buffers.delete(type);
                    return null;
                });
            buffers.set(type, pending);
        }
        return buffers.get(type);
    }

    // Keyboard scanners and pointer input both unlock audio before AJAX finishes.
    function prepare() {
        try {
            const audioContext = getContext();
            if (!audioContext) return;
            if (audioContext.state === 'suspended') audioContext.resume().catch(() => {});
            loadSound('success');
            loadSound('error');
        } catch (_) {
            // Audio is optional; validation and saving must remain available.
        }
    }

    async function play(type) {
        const request = ++latestRequest;
        try {
            if (activeSource) {
                activeSource.stop();
                activeSource = null;
            }
            const audioContext = getContext();
            if (!audioContext) return;
            await audioContext.resume();
            const buffer = await loadSound(type);
            if (!buffer || request !== latestRequest) return;

            const source = audioContext.createBufferSource();
            source.buffer = buffer;
            source.connect(outputGain);
            source.onended = () => {
                source.disconnect();
                if (activeSource === source) activeSource = null;
            };
            activeSource = source;
            source.start();
        } catch (_) {
            // Missing files or browser restrictions must not interrupt the scan.
        }
    }

    document.addEventListener('pointerdown', prepare, { capture: true });
    document.addEventListener('keydown', prepare, { capture: true });
    window.ScanFeedback = {
        success: () => play('success'),
        error: () => play('error'),
    };
})();
