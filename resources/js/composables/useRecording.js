import { ref, computed } from 'vue';
import { useFrontendVad } from './useFrontendVad.js';

const MIME_CANDIDATES = [
    'audio/webm;codecs=opus',
    'audio/webm',
    'audio/ogg;codecs=opus',
    'audio/ogg',
    'audio/mp4',
    'audio/mpeg',
];

export function resolveFileExtension(mimeType) {
    const t = (mimeType || '').toLowerCase();
    if (t.includes('ogg')) return 'ogg';
    if (t.includes('wav')) return 'wav';
    if (t.includes('mp4') || t.includes('m4a')) return 'm4a';
    if (t.includes('mpeg') || t.includes('mp3')) return 'mp3';
    return 'webm';
}

function resolveRecorderMimeType() {
    if (typeof window.MediaRecorder === 'undefined') return '';
    if (typeof window.MediaRecorder.isTypeSupported !== 'function') return MIME_CANDIDATES[0];
    return MIME_CANDIDATES.find((c) => window.MediaRecorder.isTypeSupported(c)) ?? '';
}

export function useRecording() {
    const stream = ref(null);
    const recorder = ref(null);
    const recordingMode = ref(null);
    const chunks = ref([]);

    const mediaRecorderSupported = typeof window.MediaRecorder !== 'undefined';
    const canRequestMicrophone = typeof navigator.mediaDevices?.getUserMedia === 'function';
    const recordingSupported = computed(() => mediaRecorderSupported && canRequestMicrophone);
    const { detectSpeech } = useFrontendVad();

    const hasValidStream = () => stream.value instanceof MediaStream;

    const stopAllTracks = () => {
        if (stream.value instanceof MediaStream) {
            stream.value.getTracks().forEach((track) => track.stop());
            stream.value = null;
        }
    };

    const ensureMicrophoneAccess = async () => {
        if (!recordingSupported.value) return false;
        if (hasValidStream()) return true;
        try {
            stream.value = await navigator.mediaDevices.getUserMedia({ audio: true });
            return true;
        } catch {
            return false;
        }
    };

    const stopRecording = () => {
        if (recorder.value instanceof MediaRecorder && recorder.value.state !== 'inactive') {
            recorder.value.stop();
        }
    };

    const startRecording = async (mode, callbacks = {}) => {
        const { onStart, onStop, onError } = callbacks;

        if (!recordingSupported.value) {
            onError?.('Запись звука недоступна в этом браузере.');
            return;
        }

        const hasAccess = await ensureMicrophoneAccess();
        if (!hasAccess || !hasValidStream()) {
            onError?.('Не удалось получить доступ к микрофону.');
            return;
        }

        const mimeType = resolveRecorderMimeType();
        try {
            recorder.value = mimeType
                ? new window.MediaRecorder(stream.value, { mimeType })
                : new window.MediaRecorder(stream.value);
        } catch {
            onError?.('Не удалось начать запись.');
            return;
        }

        chunks.value = [];
        recordingMode.value = mode;
        onStart?.();

        recorder.value.ondataavailable = (e) => {
            if (e.data.size > 0) chunks.value.push(e.data);
        };

        recorder.value.onerror = () => {
            recordingMode.value = null;
            recorder.value = null;
            onError?.('Ошибка записи.');
        };

        recorder.value.onstop = async () => {
            const stoppedMode = recordingMode.value;
            recordingMode.value = null;

            const collected = chunks.value;
            chunks.value = [];
            recorder.value = null;

            const blobMime = collected[0]?.type || mimeType || 'audio/webm';
            const blob = new Blob(collected, { type: blobMime });
            collected.length = 0;

            if (blob.size === 0) {
                onError?.('Пустая запись. Попробуйте еще раз.');
                stopAllTracks();
                return;
            }

            const vadResult = await detectSpeech(blob);
            if (!vadResult.hasSpeech) {
                onError?.('Речь не обнаружена. Повторите запись и говорите чуть громче.');
                stopAllTracks();
                return;
            }

            onStop?.(blob, stoppedMode, vadResult);
        };

        recorder.value.start(1000);
    };

    const releaseStream = () => {
        stopRecording();
        stopAllTracks();
    };

    return {
        stream,
        recorder,
        recordingMode,
        recordingSupported,
        ensureMicrophoneAccess,
        startRecording,
        stopRecording,
        releaseStream,
        resolveFileExtension,
    };
}
