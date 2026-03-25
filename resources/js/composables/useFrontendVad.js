const DEFAULT_MIN_SPEECH_SECONDS = 0.15;
const RMS_SILENCE_THRESHOLD = 0.005;
const ACTIVE_FRAME_THRESHOLD = 0.007;
const MIN_ACTIVE_RATIO = 0.02;

export function sumSpeechDurationSeconds(segments) {
    if (!Array.isArray(segments)) {
        return 0;
    }

    return segments.reduce((total, segment) => {
        const startMs = Number(segment?.start);
        const endMs = Number(segment?.end);
        if (!Number.isFinite(startMs) || !Number.isFinite(endMs) || endMs <= startMs) {
            return total;
        }

        return total + (endMs - startMs) / 1000;
    }, 0);
}

export function shouldTreatAsSpeech(speechSeconds, minSpeechSeconds = DEFAULT_MIN_SPEECH_SECONDS) {
    if (!Number.isFinite(speechSeconds)) {
        return false;
    }

    return speechSeconds >= Math.max(0, Number(minSpeechSeconds) || DEFAULT_MIN_SPEECH_SECONDS);
}

function getAudioContextConstructor() {
    return window.AudioContext || window.webkitAudioContext || null;
}

function analyzeEnergy(pcmData, sampleRate) {
    if (!(pcmData instanceof Float32Array) || pcmData.length === 0 || sampleRate <= 0) {
        return { rms: 0, activeRatio: 0, audioDurationSeconds: 0 };
    }

    const audioDurationSeconds = pcmData.length / sampleRate;
    const frameSize = Math.floor(sampleRate * 0.03);
    const frameCount = Math.floor(pcmData.length / frameSize);

    if (frameCount === 0) {
        return { rms: 0, activeRatio: 0, audioDurationSeconds };
    }

    let totalEnergy = 0;
    let activeFrames = 0;

    for (let f = 0; f < frameCount; f++) {
        let frameEnergy = 0;
        const offset = f * frameSize;
        for (let i = 0; i < frameSize; i++) {
            const sample = pcmData[offset + i];
            frameEnergy += sample * sample;
        }
        const frameRms = Math.sqrt(frameEnergy / frameSize);
        totalEnergy += frameEnergy;

        if (frameRms > ACTIVE_FRAME_THRESHOLD) {
            activeFrames++;
        }
    }

    return {
        rms: Math.sqrt(totalEnergy / pcmData.length),
        activeRatio: activeFrames / frameCount,
        audioDurationSeconds,
    };
}

function decodeToMono(decoded) {
    const channelCount = decoded.numberOfChannels || 0;
    const frameCount = decoded.length || 0;

    if (channelCount <= 0 || frameCount <= 0) {
        return new Float32Array();
    }

    if (channelCount === 1) {
        return new Float32Array(decoded.getChannelData(0));
    }

    const mono = new Float32Array(frameCount);
    for (let ch = 0; ch < channelCount; ch++) {
        const data = decoded.getChannelData(ch);
        for (let i = 0; i < frameCount; i++) {
            mono[i] += data[i] / channelCount;
        }
    }

    return mono;
}

export function useFrontendVad() {
    const detectSpeech = async (audioBlob, options = {}) => {
        const minSpeechSeconds = Number(options.minSpeechSeconds) || DEFAULT_MIN_SPEECH_SECONDS;

        if (!(audioBlob instanceof Blob)) {
            return {
                hasSpeech: false,
                reason: 'invalid_audio_blob',
                speechSeconds: 0,
                audioDurationSeconds: 0,
            };
        }

        const AudioContextCtor = getAudioContextConstructor();
        if (!AudioContextCtor) {
            return { hasSpeech: true, reason: null, speechSeconds: 0, audioDurationSeconds: 0 };
        }

        let audioContext = null;

        try {
            let arrayBuffer = await audioBlob.arrayBuffer();
            audioContext = new AudioContextCtor();
            const decoded = await audioContext.decodeAudioData(arrayBuffer);
            arrayBuffer = null;

            const sampleRate = decoded.sampleRate;
            const mono = decodeToMono(decoded);

            const { rms, activeRatio, audioDurationSeconds } = analyzeEnergy(mono, sampleRate);

            if (rms < RMS_SILENCE_THRESHOLD) {
                return {
                    hasSpeech: false,
                    reason: 'below_rms_threshold',
                    speechSeconds: 0,
                    audioDurationSeconds,
                };
            }

            if (activeRatio < MIN_ACTIVE_RATIO) {
                return {
                    hasSpeech: false,
                    reason: 'below_min_speech_threshold',
                    speechSeconds: 0,
                    audioDurationSeconds,
                };
            }

            const estimatedSpeechSeconds = audioDurationSeconds * activeRatio;
            const hasSpeech = shouldTreatAsSpeech(estimatedSpeechSeconds, minSpeechSeconds);

            return {
                hasSpeech,
                reason: hasSpeech ? null : 'below_min_speech_threshold',
                speechSeconds: estimatedSpeechSeconds,
                audioDurationSeconds,
            };
        } catch {
            return { hasSpeech: true, reason: 'decode_error_pass_through', speechSeconds: 0, audioDurationSeconds: 0 };
        } finally {
            if (audioContext) {
                await audioContext.close().catch(() => {});
            }
        }
    };

    return {
        detectSpeech,
    };
}
