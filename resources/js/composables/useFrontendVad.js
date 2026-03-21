import { NonRealTimeVAD } from '@ricky0123/vad-web';

const DEFAULT_MIN_SPEECH_SECONDS = 0.5;
const VAD_MODEL_URL = '/vad/silero_vad_legacy.onnx';
const ORT_WASM_BASE_PATH = '/vad/';

let nonRealTimeVadPromise = null;

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

function getAudioDurationSeconds(audioBuffer) {
    const frameCount = Number(audioBuffer?.length) || 0;
    const sampleRate = Number(audioBuffer?.sampleRate) || 0;

    if (frameCount <= 0 || sampleRate <= 0) {
        return 0;
    }

    return frameCount / sampleRate;
}

function mixToMono(audioBuffer) {
    const channelCount = Number(audioBuffer?.numberOfChannels) || 0;
    const frameCount = Number(audioBuffer?.length) || 0;

    if (channelCount <= 0 || frameCount <= 0) {
        return new Float32Array();
    }

    if (channelCount === 1) {
        const mono = audioBuffer.getChannelData(0);
        return new Float32Array(mono);
    }

    const monoData = new Float32Array(frameCount);
    for (let channel = 0; channel < channelCount; channel += 1) {
        const channelData = audioBuffer.getChannelData(channel);
        for (let i = 0; i < frameCount; i += 1) {
            monoData[i] += channelData[i] / channelCount;
        }
    }

    return monoData;
}

async function decodeBlobToMonoPcm(audioBlob) {
    const AudioContextCtor = getAudioContextConstructor();
    if (!AudioContextCtor) {
        throw new Error('AudioContext is not supported.');
    }

    const arrayBuffer = await audioBlob.arrayBuffer();
    const audioContext = new AudioContextCtor();

    try {
        const decodedBuffer = await audioContext.decodeAudioData(arrayBuffer);
        return {
            monoPcm: mixToMono(decodedBuffer),
            sampleRate: decodedBuffer.sampleRate,
            audioDurationSeconds: getAudioDurationSeconds(decodedBuffer),
        };
    } finally {
        await audioContext.close().catch(() => {});
    }
}

async function getNonRealTimeVad() {
    if (!nonRealTimeVadPromise) {
        nonRealTimeVadPromise = NonRealTimeVAD.new({
            minSpeechMs: 500,
            modelURL: VAD_MODEL_URL,
            ortConfig: (ort) => {
                ort.env.wasm.wasmPaths = ORT_WASM_BASE_PATH;
                ort.env.logLevel = 'error';
            },
        });
    }

    return nonRealTimeVadPromise;
}

function normalizeErrorReason(error) {
    const message = error instanceof Error ? error.message : '';
    return message === '' ? 'vad_error' : `vad_error:${message}`;
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

        try {
            const { monoPcm, sampleRate, audioDurationSeconds } = await decodeBlobToMonoPcm(audioBlob);

            if (!(monoPcm instanceof Float32Array) || monoPcm.length === 0 || !Number.isFinite(sampleRate)) {
                return {
                    hasSpeech: false,
                    reason: 'audio_decode_empty',
                    speechSeconds: 0,
                    audioDurationSeconds,
                };
            }

            const detector = await getNonRealTimeVad();
            const speechSegments = [];

            for await (const segment of detector.run(monoPcm, sampleRate)) {
                speechSegments.push(segment);
            }

            const speechSeconds = sumSpeechDurationSeconds(speechSegments);
            const hasSpeech = shouldTreatAsSpeech(speechSeconds, minSpeechSeconds);

            return {
                hasSpeech,
                reason: hasSpeech ? null : 'below_min_speech_threshold',
                speechSeconds,
                audioDurationSeconds,
            };
        } catch (error) {
            return {
                hasSpeech: false,
                reason: normalizeErrorReason(error),
                speechSeconds: 0,
                audioDurationSeconds: 0,
            };
        }
    };

    return {
        detectSpeech,
    };
}
