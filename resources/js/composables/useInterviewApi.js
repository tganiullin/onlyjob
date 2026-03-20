import { resolveFileExtension } from './useRecording.js';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function useInterviewApi(config) {
    const { transcribeEndpoint, answerEndpointTemplate, feedbackEndpoint } = config;

    const transcribe = async (audioBlob) => {
        const csrf = getCsrfToken();
        if (!csrf) throw new Error('Не найден CSRF токен. Обновите страницу.');
        if (!transcribeEndpoint) throw new Error('Маршрут транскрибации не настроен.');

        const ext = resolveFileExtension(audioBlob.type || '');
        const formData = new FormData();
        formData.append('audio', audioBlob, `recording.${ext}`);
        formData.append('language', 'auto');

        const res = await fetch(transcribeEndpoint, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: formData,
        });

        const payload = await res.json().catch(() => null);
        if (!res.ok) {
            const msg =
                payload?.errors?.audio?.[0] ??
                payload?.errors?.language?.[0] ??
                payload?.message ??
                'Не удалось распознать аудио.';
            throw new Error(msg);
        }
        if (!payload || typeof payload.text !== 'string') {
            throw new Error('Некорректный ответ сервера распознавания.');
        }
        return payload.text.trim();
    };

    const submitAnswer = async (questionId, candidateAnswer) => {
        const csrf = getCsrfToken();
        if (!csrf) throw new Error('Не найден CSRF токен. Обновите страницу.');

        const endpoint = answerEndpointTemplate.replace('__QUESTION_ID__', String(questionId));
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ candidate_answer: candidateAnswer }),
        });

        const payload = await res.json().catch(() => ({}));
        if (!res.ok) {
            const msg =
                payload?.errors?.candidate_answer?.[0] ?? payload?.message ?? 'Не удалось сохранить ответ.';
            throw new Error(msg);
        }
        return payload;
    };

    const submitFeedback = async (candidateFeedbackRating) => {
        const csrf = getCsrfToken();
        if (!csrf) throw new Error('Не найден CSRF токен. Обновите страницу.');
        if (!feedbackEndpoint) throw new Error('Маршрут сохранения оценки не настроен.');

        const res = await fetch(feedbackEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ candidate_feedback_rating: candidateFeedbackRating }),
        });

        const payload = await res.json().catch(() => ({}));
        if (!res.ok) {
            const msg =
                payload?.errors?.candidate_feedback_rating?.[0] ??
                payload?.message ??
                'Не удалось сохранить оценку интервью.';
            throw new Error(msg);
        }

        return payload;
    };

    return { transcribe, submitAnswer, submitFeedback };
}
