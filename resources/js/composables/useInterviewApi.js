import { resolveFileExtension } from './useRecording.js';

const POLL_INTERVAL_MS = 1500;
const POLL_MAX_ATTEMPTS = 60;
const BODY_PREVIEW_LIMIT = 500;

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

export class TranscribeError extends Error {
    constructor(message, { isTransient = false, stage = 'unknown', httpStatus = null, contentType = null, bodyPreview = null, cause = null } = {}) {
        super(message);
        this.name = 'TranscribeError';
        this.isTransient = isTransient;
        this.stage = stage;
        this.httpStatus = httpStatus;
        this.contentType = contentType;
        this.bodyPreview = bodyPreview;
        if (cause) this.cause = cause;
    }
}

async function readResponseBody(res) {
    let raw = '';
    try {
        raw = await res.text();
    } catch {
        raw = '';
    }
    const contentType = res.headers?.get?.('content-type') ?? null;
    let payload = null;
    if (raw !== '') {
        try {
            payload = JSON.parse(raw);
        } catch {
            payload = null;
        }
    }
    return { raw, payload, contentType, bodyPreview: raw.slice(0, BODY_PREVIEW_LIMIT) };
}

export function useInterviewApi(config) {
    const { transcribeEndpoint, answerEndpointTemplate, skipEndpointTemplate, feedbackEndpoint, customQuestionEndpoint, integritySignalEndpoint } = config;

    const transcribe = async (audioBlob, interviewQuestionId = null) => {
        const csrf = getCsrfToken();
        if (!csrf) {
            throw new TranscribeError('Не найден CSRF токен. Обновите страницу.', { isTransient: false, stage: 'precheck' });
        }
        if (!transcribeEndpoint) {
            throw new TranscribeError('Маршрут транскрибации не настроен.', { isTransient: false, stage: 'precheck' });
        }

        const audioType = audioBlob?.type || '';
        const audioSize = audioBlob?.size ?? 0;
        const ext = resolveFileExtension(audioType);
        const startedAt = Date.now();

        console.info('[transcribe] start', {
            audioSize,
            audioType,
            interviewQuestionId,
        });

        let formData = new FormData();
        formData.append('audio', audioBlob, `recording.${ext}`);
        formData.append('language', 'auto');

        if (interviewQuestionId !== null && interviewQuestionId !== undefined) {
            formData.append('interview_question_id', String(interviewQuestionId));
        }

        let res;
        try {
            res = await fetch(transcribeEndpoint, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: formData,
            });
        } catch (networkErr) {
            const elapsedMs = Date.now() - startedAt;
            console.error('[transcribe] upload_network_error', {
                stage: 'upload',
                audioSize,
                audioType,
                interviewQuestionId,
                elapsedMs,
                error: networkErr?.message,
            });
            throw new TranscribeError('Сетевая ошибка при отправке аудио. Проверьте соединение.', {
                isTransient: true,
                stage: 'upload',
                cause: networkErr,
            });
        } finally {
            formData = null;
        }

        const { payload: uploadPayload, contentType, bodyPreview, raw } = await readResponseBody(res);
        const elapsedUploadMs = Date.now() - startedAt;

        if (!res.ok) {
            const validationMsg =
                uploadPayload?.errors?.audio?.[0] ??
                uploadPayload?.errors?.language?.[0] ??
                uploadPayload?.errors?.interview_question_id?.[0] ??
                null;

            const isTransient = res.status >= 500 || res.status === 408 || res.status === 429 || res.status === 0;
            const message = validationMsg ?? uploadPayload?.message ?? (isTransient
                ? 'Сервер временно недоступен. Попробуйте еще раз.'
                : 'Не удалось распознать аудио.');

            console.error('[transcribe] upload_failed', {
                stage: 'upload',
                httpStatus: res.status,
                contentType,
                bodyPreview,
                elapsedMs: elapsedUploadMs,
                audioSize,
                audioType,
                interviewQuestionId,
            });

            throw new TranscribeError(message, {
                isTransient: isTransient && validationMsg === null,
                stage: 'upload',
                httpStatus: res.status,
                contentType,
                bodyPreview,
            });
        }

        if (uploadPayload?.status === 'completed') {
            console.info('[transcribe] success', {
                stage: 'upload',
                transcriptionKey: uploadPayload?.transcription_key ?? null,
                pollAttempts: 0,
                elapsedMs: elapsedUploadMs,
            });
            return (uploadPayload.text ?? '').trim();
        }

        const statusUrl = uploadPayload?.status_url;
        const transcriptionKey = uploadPayload?.transcription_key ?? null;

        if (!statusUrl) {
            console.error('[transcribe] upload_missing_status_url', {
                stage: 'upload',
                httpStatus: res.status,
                contentType,
                bodyPreview,
                bodyLength: raw.length,
                elapsedMs: elapsedUploadMs,
                audioSize,
                audioType,
                interviewQuestionId,
                hasPayload: uploadPayload !== null,
                payloadStatus: uploadPayload?.status ?? null,
                transcriptionKey,
            });
            throw new TranscribeError('Сервер не вернул адрес для проверки статуса распознавания.', {
                isTransient: true,
                stage: 'upload_missing_status_url',
                httpStatus: res.status,
                contentType,
                bodyPreview,
            });
        }

        for (let attempt = 0; attempt < POLL_MAX_ATTEMPTS; attempt++) {
            await sleep(POLL_INTERVAL_MS);

            let pollRes;
            try {
                pollRes = await fetch(statusUrl, {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                });
            } catch (networkErr) {
                console.error('[transcribe] poll_network_error', {
                    stage: 'poll',
                    attempt,
                    transcriptionKey,
                    elapsedMs: Date.now() - startedAt,
                    error: networkErr?.message,
                });
                throw new TranscribeError('Сетевая ошибка при проверке статуса распознавания.', {
                    isTransient: true,
                    stage: 'poll',
                    cause: networkErr,
                });
            }

            const { payload: pollPayload, contentType: pollContentType, bodyPreview: pollBodyPreview } = await readResponseBody(pollRes);

            if (!pollRes.ok && pollRes.status !== 404) {
                const isTransient = pollRes.status >= 500 || pollRes.status === 408 || pollRes.status === 429;
                console.error('[transcribe] poll_failed', {
                    stage: 'poll',
                    httpStatus: pollRes.status,
                    contentType: pollContentType,
                    bodyPreview: pollBodyPreview,
                    attempt,
                    transcriptionKey,
                });
                throw new TranscribeError(pollPayload?.message ?? 'Ошибка при проверке статуса распознавания.', {
                    isTransient,
                    stage: 'poll',
                    httpStatus: pollRes.status,
                    contentType: pollContentType,
                    bodyPreview: pollBodyPreview,
                });
            }

            if (pollPayload?.status === 'completed') {
                console.info('[transcribe] success', {
                    stage: 'poll',
                    transcriptionKey,
                    pollAttempts: attempt + 1,
                    elapsedMs: Date.now() - startedAt,
                });
                return (pollPayload.text ?? '').trim();
            }

            if (pollPayload?.status === 'failed') {
                console.error('[transcribe] poll_status_failed', {
                    stage: 'poll',
                    transcriptionKey,
                    attempt,
                    error: pollPayload?.error,
                });
                throw new TranscribeError(pollPayload.error ?? 'Не удалось распознать аудио.', {
                    isTransient: true,
                    stage: 'poll_failed',
                });
            }
        }

        console.error('[transcribe] poll_timeout', {
            stage: 'poll',
            transcriptionKey,
            pollAttempts: POLL_MAX_ATTEMPTS,
            elapsedMs: Date.now() - startedAt,
        });
        throw new TranscribeError('Превышено время ожидания распознавания. Попробуйте еще раз.', {
            isTransient: true,
            stage: 'poll_timeout',
        });
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

    const skipAnswer = async (questionId) => {
        const csrf = getCsrfToken();
        if (!csrf) throw new Error('Не найден CSRF токен. Обновите страницу.');

        const endpoint = skipEndpointTemplate.replace('__QUESTION_ID__', String(questionId));
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({}),
        });

        const payload = await res.json().catch(() => ({}));
        if (!res.ok) {
            const msg = payload?.message ?? 'Не удалось пропустить вопрос.';
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

    return { transcribe, submitAnswer, skipAnswer, submitFeedback, submitCustomQuestion, submitIntegritySignal, pollFollowUp };
}
