import { resolveFileExtension } from './useRecording.js';

const POLL_INTERVAL_MS = 1500;
const POLL_MAX_ATTEMPTS = 60;

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

export function useInterviewApi(config) {
    const { transcribeEndpoint, answerEndpointTemplate, feedbackEndpoint, customQuestionEndpoint, integritySignalEndpoint } = config;

    const transcribe = async (audioBlob, interviewQuestionId = null) => {
        const csrf = getCsrfToken();
        if (!csrf) throw new Error('Не найден CSRF токен. Обновите страницу.');
        if (!transcribeEndpoint) throw new Error('Маршрут транскрибации не настроен.');

        const ext = resolveFileExtension(audioBlob.type || '');
        let formData = new FormData();
        formData.append('audio', audioBlob, `recording.${ext}`);
        formData.append('language', 'auto');

        if (interviewQuestionId !== null && interviewQuestionId !== undefined) {
            formData.append('interview_question_id', String(interviewQuestionId));
        }

        const res = await fetch(transcribeEndpoint, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: formData,
        });

        formData = null;

        const uploadPayload = await res.json().catch(() => null);
        if (!res.ok) {
            const msg =
                uploadPayload?.errors?.audio?.[0] ??
                uploadPayload?.errors?.language?.[0] ??
                uploadPayload?.message ??
                'Не удалось распознать аудио.';
            throw new Error(msg);
        }

        if (uploadPayload?.status === 'completed') {
            return (uploadPayload.text ?? '').trim();
        }

        const statusUrl = uploadPayload?.status_url;
        if (!statusUrl) {
            throw new Error('Сервер не вернул адрес для проверки статуса распознавания.');
        }

        for (let attempt = 0; attempt < POLL_MAX_ATTEMPTS; attempt++) {
            await sleep(POLL_INTERVAL_MS);

            const pollRes = await fetch(statusUrl, {
                method: 'GET',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            });

            const pollPayload = await pollRes.json().catch(() => null);

            if (!pollRes.ok && pollRes.status !== 404) {
                throw new Error(pollPayload?.message ?? 'Ошибка при проверке статуса распознавания.');
            }

            if (pollPayload?.status === 'completed') {
                return (pollPayload.text ?? '').trim();
            }

            if (pollPayload?.status === 'failed') {
                throw new Error(pollPayload.error ?? 'Не удалось распознать аудио.');
            }
        }

        throw new Error('Превышено время ожидания распознавания. Попробуйте еще раз.');
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

    const submitCustomQuestion = async (candidateCustomQuestion) => {
        const csrf = getCsrfToken();
        if (!csrf) throw new Error('Не найден CSRF токен. Обновите страницу.');
        if (!customQuestionEndpoint) throw new Error('Маршрут отправки вопроса не настроен.');

        const res = await fetch(customQuestionEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ candidate_custom_question: candidateCustomQuestion }),
        });

        const payload = await res.json().catch(() => ({}));
        if (!res.ok) {
            const msg =
                payload?.errors?.candidate_custom_question?.[0] ??
                payload?.message ??
                'Не удалось отправить вопрос.';
            throw new Error(msg);
        }

        return payload;
    };

    const submitIntegritySignal = async ({
        eventType,
        occurredAt,
        interviewQuestionId = null,
        payload = {},
    }) => {
        if (!integritySignalEndpoint) {
            return;
        }

        const csrf = getCsrfToken();
        if (!csrf) {
            return;
        }

        await fetch(integritySignalEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                event_type: eventType,
                occurred_at: occurredAt,
                interview_question_id: interviewQuestionId,
                payload,
            }),
        });
    };

    const pollFollowUp = async (statusUrl) => {
        for (let attempt = 0; attempt < POLL_MAX_ATTEMPTS; attempt++) {
            await sleep(POLL_INTERVAL_MS);

            const pollRes = await fetch(statusUrl, {
                method: 'GET',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            });

            const pollPayload = await pollRes.json().catch(() => null);

            if (!pollRes.ok && pollRes.status !== 404) {
                return { status: 'failed', needs_follow_up: false };
            }

            if (pollPayload?.status === 'completed' || pollPayload?.status === 'failed') {
                return pollPayload;
            }
        }

        return { status: 'failed', needs_follow_up: false };
    };

    return { transcribe, submitAnswer, submitFeedback, submitCustomQuestion, submitIntegritySignal, pollFollowUp };
}
