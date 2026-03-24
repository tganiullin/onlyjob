<script setup>
import { ref, computed, reactive, watch, onMounted, onUnmounted } from 'vue';
import ScreenStart from './components/interview/ScreenStart.vue';
import ScreenChat from './components/interview/ScreenChat.vue';
import { useQuestionTimer } from './composables/useQuestionTimer.js';
import { useRecording } from './composables/useRecording.js';
import { useInterviewApi } from './composables/useInterviewApi.js';

const props = defineProps({
    questions: { type: Array, default: () => [] },
    companyQuestions: { type: Array, default: () => [] },
    answerEndpointTemplate: { type: String, default: '' },
    transcribeEndpoint: { type: String, default: '' },
    feedbackEndpoint: { type: String, default: '' },
    customQuestionEndpoint: { type: String, default: '' },
    integritySignalEndpoint: { type: String, default: '' },
    answerTimeSeconds: { type: Number, default: 120 },
    interviewCompleted: { type: Boolean, default: false },
    completionMessage: { type: String, default: 'Спасибо! Вы успешно завершили первый этап интервью.' },
    firstName: { type: String, default: '' },
    lastName: { type: String, default: '' },
    positionTitle: { type: String, default: '' },
    answerTimeLabel: { type: String, default: '2 минуты' },
    logoUrl: { type: String, default: '' },
    initialCandidateFeedbackRating: { type: Number, default: null },
    initialCandidateCustomQuestion: { type: String, default: '' },
});

const questions = computed(() => Array.isArray(props.questions) ? props.questions : []);

const firstUnansweredIndex = computed(() =>
    questions.value.findIndex((q) => !(typeof q?.candidate_answer === 'string' && q.candidate_answer.trim() !== '')),
);
const allAnswered = computed(() => questions.value.length > 0 && firstUnansweredIndex.value === -1);

const completed = ref(props.interviewCompleted || allAnswered.value);
const currentIndex = ref(
    completed.value ? questions.value.length : Math.max(firstUnansweredIndex.value, 0),
);

const submittedAnswers = reactive({});
questions.value.forEach((q) => {
    if (q?.id != null && typeof q?.candidate_answer === 'string' && q.candidate_answer.trim() !== '') {
        submittedAnswers[q.id] = q.candidate_answer.trim();
    }
});

const currentScreen = ref(completed.value ? 'interview' : 'start');
const showInstructionsInChat = ref(false);
const showHelpModal = ref(false);

const phraseCompleted = ref(false);
const phraseResult = ref('');
const microphoneStatus = ref('');
const microphoneStatusError = ref(false);
const hasMicrophoneAccess = ref(false);
const transcribing = ref(false);
const submitting = ref(false);
const skipSubmitting = ref(false);
const answerStatus = ref(completed.value ? props.completionMessage : '');
const answerStatusError = ref(false);
const feedbackRating = ref(
    Number.isInteger(props.initialCandidateFeedbackRating) &&
    props.initialCandidateFeedbackRating >= 1 &&
    props.initialCandidateFeedbackRating <= 5
        ? props.initialCandidateFeedbackRating
        : null,
);
const feedbackSubmitting = ref(false);
const feedbackStatus = ref('');
const feedbackStatusError = ref(false);

const customQuestion = ref(
    typeof props.initialCandidateCustomQuestion === 'string' && props.initialCandidateCustomQuestion.trim() !== ''
        ? props.initialCandidateCustomQuestion.trim()
        : '',
);
const customQuestionSubmitting = ref(false);
const customQuestionStatus = ref('');
const customQuestionStatusError = ref(false);

const api = useInterviewApi({
    transcribeEndpoint: props.transcribeEndpoint,
    answerEndpointTemplate: props.answerEndpointTemplate,
    feedbackEndpoint: props.feedbackEndpoint,
    customQuestionEndpoint: props.customQuestionEndpoint,
    integritySignalEndpoint: props.integritySignalEndpoint,
});

const { remainingSeconds, formatTimer, start: startTimer, stop: stopTimer } = useQuestionTimer(
    Math.max(props.answerTimeSeconds, 1),
);

const {
    recordingMode,
    recordingSupported,
    ensureMicrophoneAccess,
    startRecording,
    stopRecording,
} = useRecording();

const isRecordingPhrase = computed(() => recordingMode.value === 'phrase');
const isRecordingAnswer = computed(() => recordingMode.value === 'answer');

const showRecordHint = ref(false);
const highlightRecordButton = ref(false);

const answeredByRecordingQuestionIds = new Set();
let recordHintTimeoutId = null;
let tabHiddenStartedAt = null;

function shouldTrackIntegritySignals() {
    return props.integritySignalEndpoint !== '' && currentScreen.value === 'interview' && !completed.value;
}

function sendIntegritySignal(eventType, payload = {}) {
    if (!shouldTrackIntegritySignals()) {
        return;
    }

    const occurredAt = new Date().toISOString();
    const interviewQuestionId = resolveCurrentQuestionId();

    void api.submitIntegritySignal({
        eventType,
        occurredAt,
        interviewQuestionId,
        payload,
    }).catch(() => {});
}

function handleDocumentVisibilityChange() {
    if (!shouldTrackIntegritySignals()) {
        return;
    }

    if (document.hidden) {
        tabHiddenStartedAt = Date.now();
        sendIntegritySignal('tab_hidden', {
            current_screen: currentScreen.value,
        });

        return;
    }

    const hiddenForMs = tabHiddenStartedAt === null ? null : Math.max(0, Date.now() - tabHiddenStartedAt);
    tabHiddenStartedAt = null;

    sendIntegritySignal('tab_visible', {
        hidden_for_ms: hiddenForMs,
        current_screen: currentScreen.value,
    });
}

function clearRecordHintTimer() {
    if (recordHintTimeoutId !== null) {
        window.clearTimeout(recordHintTimeoutId);
        recordHintTimeoutId = null;
    }
}

function resetRecordHint() {
    clearRecordHintTimer();
    showRecordHint.value = false;
    highlightRecordButton.value = false;
}

function resolveCurrentQuestionId() {
    return questions.value[currentIndex.value]?.id ?? null;
}

function shouldShowRecordAttention() {
    const questionId = resolveCurrentQuestionId();
    return (
        currentScreen.value === 'interview' &&
        !completed.value &&
        questionId !== null &&
        !isRecordingAnswer.value &&
        !transcribing.value &&
        !submitting.value &&
        !skipSubmitting.value &&
        !answeredByRecordingQuestionIds.has(String(questionId))
    );
}

function scheduleRecordHint() {
    resetRecordHint();
    if (!shouldShowRecordAttention()) {
        return;
    }

    highlightRecordButton.value = true;

    recordHintTimeoutId = window.setTimeout(() => {
        if (shouldShowRecordAttention()) {
            showRecordHint.value = true;
        }
    }, 8000);
}

function handleStartFlow() {
    currentScreen.value = 'chat';
}

async function handleRequestMicrophone() {
    if (!recordingSupported.value) {
        microphoneStatus.value = 'Запись звука недоступна в этом браузере.';
        microphoneStatusError.value = true;
        return;
    }
    const ok = await ensureMicrophoneAccess();
    if (ok) {
        microphoneStatus.value = 'Микрофон доступен. Можно продолжать.';
        microphoneStatusError.value = false;
        hasMicrophoneAccess.value = true;
    } else {
        microphoneStatus.value = 'Не удалось получить доступ к микрофону.';
        microphoneStatusError.value = true;
    }
}

function handleTogglePhraseRecord() {
    if (isRecordingPhrase.value) {
        stopRecording();
        return;
    }
    startRecording('phrase', {
        onStart: () => {
            microphoneStatus.value = 'Идет запись фразы...';
            microphoneStatusError.value = false;
        },
        onStop: async (blob, _mode, _vadResult) => {
            transcribing.value = true;
            microphoneStatus.value = 'Распознаю тестовую фразу...';
            try {
                const text = await api.transcribe(blob);
                phraseResult.value = text === '' ? 'Не удалось распознать фразу.' : text;
                phraseCompleted.value = true;
                microphoneStatus.value = text === '' ? 'Фраза не распознана, попробуйте снова.' : 'Отлично, все работает.';
                microphoneStatusError.value = text === '';
            } catch (err) {
                microphoneStatus.value = err instanceof Error ? err.message : 'Не удалось обработать аудио.';
                microphoneStatusError.value = true;
            } finally {
                transcribing.value = false;
            }
        },
        onError: (msg) => {
            microphoneStatus.value = msg;
            microphoneStatusError.value = true;
        },
    });
}

function handleChatContinue() {
    if (!phraseCompleted.value) {
        microphoneStatus.value = 'Сначала запишите тестовую фразу.';
        microphoneStatusError.value = true;
        return;
    }
    showInstructionsInChat.value = true;
}

function handleInstructionsStart() {
    currentScreen.value = 'interview';
    startTimer(
        () => {},
        () => {
            answerStatus.value = 'Время на вопрос истекло. Запишите ответ или выберите "Не знаю ответ".';
            answerStatusError.value = true;
        },
    );
}

async function submitAnswer(candidateAnswer) {
    const question = questions.value[currentIndex.value];
    if (!question || submitting.value) return;

    submitting.value = true;
    answerStatus.value = 'Сохраняем ответ...';
    answerStatusError.value = false;
    stopTimer();

    try {
        const payload = await api.submitAnswer(question.id, candidateAnswer);
        submittedAnswers[question.id] = candidateAnswer;

        if (payload.completed === true) {
            completed.value = true;
            currentIndex.value = questions.value.length;
            answerStatus.value =
                typeof payload.message === 'string' && payload.message.trim() !== ''
                    ? payload.message.trim()
                    : props.completionMessage;
            return;
        }

        if (payload.next_question?.id) {
            const nextIdx = questions.value.findIndex((q) => Number(q.id) === Number(payload.next_question.id));
            currentIndex.value = nextIdx >= 0 ? nextIdx : currentIndex.value + 1;
        } else {
            currentIndex.value += 1;
        }

        answerStatus.value = 'Ответ сохранен.';
        startTimer(
            () => {},
            () => {
                answerStatus.value = 'Время на вопрос истекло. Запишите ответ или выберите "Не знаю ответ".';
                answerStatusError.value = true;
            },
        );
    } catch (err) {
        answerStatus.value = err instanceof Error ? err.message : 'Ошибка сети при сохранении ответа.';
        answerStatusError.value = true;
        startTimer(
            () => {},
            () => {
                answerStatus.value = 'Время на вопрос истекло. Запишите ответ или выберите "Не знаю ответ".';
                answerStatusError.value = true;
            },
        );
    } finally {
        submitting.value = false;
        if (candidateAnswer === 'Не знаю ответ') {
            skipSubmitting.value = false;
        }
    }
}

function handleRecordToggle() {
    if (isRecordingAnswer.value) {
        stopRecording();
        return;
    }

    if (!recordingSupported.value) {
        const fallback = window.prompt('Запись недоступна. Введите ответ вручную:') ?? '';
        if (fallback.trim() !== '') {
            submitAnswer(fallback.trim());
        }
        return;
    }

    startRecording('answer', {
        onStart: () => {
            const currentQuestionId = resolveCurrentQuestionId();
            if (currentQuestionId !== null) {
                answeredByRecordingQuestionIds.add(String(currentQuestionId));
            }
            resetRecordHint();
            answerStatus.value = 'Идет запись ответа...';
            answerStatusError.value = false;
        },
        onStop: async (blob, _mode, _vadResult) => {
            transcribing.value = true;
            answerStatus.value = 'Распознаю ответ...';
            answerStatusError.value = false;
            try {
                const text = await api.transcribe(blob, resolveCurrentQuestionId());
                const normalized = text === '' ? 'Не знаю ответ' : text;
                await submitAnswer(normalized);
            } catch (err) {
                answerStatus.value = err instanceof Error ? err.message : 'Не удалось обработать аудио.';
                answerStatusError.value = true;
                startTimer(
                    () => {},
                    () => {
                        answerStatus.value = 'Время на вопрос истекло. Запишите ответ или выберите "Не знаю ответ".';
                        answerStatusError.value = true;
                    },
                );
            } finally {
                transcribing.value = false;
            }
        },
        onError: (msg) => {
            answerStatus.value = msg;
            answerStatusError.value = true;
            scheduleRecordHint();
        },
    });
}

function handleSkipAnswer() {
    skipSubmitting.value = true;
    submitAnswer('Не знаю ответ');
}

async function handleFeedbackSelect(rating) {
    if (feedbackSubmitting.value) {
        return;
    }

    feedbackSubmitting.value = true;
    feedbackStatus.value = 'Сохраняем вашу оценку...';
    feedbackStatusError.value = false;

    try {
        await api.submitFeedback(rating);
        feedbackRating.value = rating;
        feedbackStatus.value = 'Спасибо! Ваша оценка сохранена.';
    } catch (err) {
        feedbackStatus.value = err instanceof Error ? err.message : 'Не удалось сохранить оценку.';
        feedbackStatusError.value = true;
    } finally {
        feedbackSubmitting.value = false;
    }
}

async function handleCustomQuestionSubmit(questionText) {
    if (customQuestionSubmitting.value) {
        return;
    }

    customQuestionSubmitting.value = true;
    customQuestionStatus.value = 'Отправляем ваш вопрос...';
    customQuestionStatusError.value = false;

    try {
        await api.submitCustomQuestion(questionText);
        customQuestion.value = questionText;
        customQuestionStatus.value = '';
    } catch (err) {
        customQuestionStatus.value = err instanceof Error ? err.message : 'Не удалось отправить вопрос.';
        customQuestionStatusError.value = true;
    } finally {
        customQuestionSubmitting.value = false;
    }
}

watch(
    () => [
        currentScreen.value,
        completed.value,
        currentIndex.value,
        isRecordingAnswer.value,
        transcribing.value,
        submitting.value,
        skipSubmitting.value,
    ],
    () => {
        scheduleRecordHint();
    },
    { immediate: true },
);

onMounted(() => {
    document.addEventListener('visibilitychange', handleDocumentVisibilityChange);
});

onUnmounted(() => {
    clearRecordHintTimer();
    document.removeEventListener('visibilitychange', handleDocumentVisibilityChange);
});
</script>

<template>
    <div class="relative min-h-screen overflow-hidden font-sans">
        <main class="min-h-screen">
            <header class="mx-auto flex w-full max-w-[1080px] items-center justify-between gap-4 px-6 pt-6 sm:px-10">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    alt="Логотип компании"
                    class="h-10 w-auto sm:h-12"
                >
                <div v-else class="h-10 sm:h-12"></div>

                <button
                    type="button"
                    class="text-sm text-[#61678b] transition-colors hover:text-[#464c72]"
                    @click="showHelpModal = true"
                >
                    ⓘ Помощь
                </button>
            </header>

            <div class="mx-auto w-full max-w-[1080px] px-6 pb-12 pt-8 sm:px-10">
                <ScreenStart
                    v-show="currentScreen === 'start'"
                    :first-name="firstName"
                    :last-name="lastName"
                    :position-title="positionTitle"
                    :questions-count="questions.length"
                    :answer-time-seconds="answerTimeSeconds"
                    @start="handleStartFlow"
                />

                <ScreenChat
                    v-if="currentScreen === 'chat' || currentScreen === 'interview'"
                    :first-name="firstName"
                    :has-microphone-access="hasMicrophoneAccess"
                    :show-instructions-message="showInstructionsInChat"
                    :interview-started="currentScreen === 'interview'"
                    :questions="questions"
                    :company-questions="companyQuestions"
                    :current-question-index="currentIndex"
                    :interview-completed="completed"
                    :submitted-answers="submittedAnswers"
                    :remaining-seconds="remainingSeconds"
                    :format-timer="formatTimer"
                    :completion-message="completionMessage"
                    :answer-time-label="answerTimeLabel"
                    :transcribing="transcribing"
                    :phrase-completed="phraseCompleted"
                    :phrase-result="phraseResult"
                    :microphone-status="microphoneStatus"
                    :microphone-status-error="microphoneStatusError"
                    :is-recording-phrase="isRecordingPhrase"
                    :submitting="submitting"
                    :skip-submitting="skipSubmitting"
                    :is-recording-answer="isRecordingAnswer"
                    :answer-status="answerStatus"
                    :answer-status-error="answerStatusError"
                    :show-record-hint="showRecordHint"
                    :highlight-record-button="highlightRecordButton"
                    :recording-supported="recordingSupported"
                    :feedback-rating="feedbackRating"
                    :feedback-submitting="feedbackSubmitting"
                    :feedback-status="feedbackStatus"
                    :feedback-status-error="feedbackStatusError"
                    :custom-question="customQuestion"
                    :custom-question-submitting="customQuestionSubmitting"
                    :custom-question-status="customQuestionStatus"
                    :custom-question-status-error="customQuestionStatusError"
                    @request-microphone="handleRequestMicrophone"
                    @toggle-phrase-record="handleTogglePhraseRecord"
                    @continue="handleChatContinue"
                    @start="handleInstructionsStart"
                    @record-toggle="handleRecordToggle"
                    @skip-answer="handleSkipAnswer"
                    @feedback-select="handleFeedbackSelect"
                    @custom-question-submit="handleCustomQuestionSubmit"
                />
            </div>
        </main>

        <Teleport to="body">
            <Transition name="help-modal">
                <div
                    v-if="showHelpModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="help-modal-title"
                >
                    <div
                        class="absolute inset-0 bg-[#1f2440]/45"
                        aria-hidden="true"
                        @click="showHelpModal = false"
                    ></div>
                    <div class="relative max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-[0_32px_80px_rgba(44,50,88,0.35)]">
                        <h2 id="help-modal-title" class="text-xl font-bold text-[#252a45]">Помощь</h2>
                        <p class="mt-4 text-base leading-relaxed text-[#4f556f]">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                        </p>
                        <p class="mt-3 text-base leading-relaxed text-[#4f556f]">
                            Curabitur pretium tincidunt lacus. Nulla facilisi. Ut fringilla. Suspendisse potenti. Nunc feugiat mi a tellus consequat imperdiet. Vestibulum sapien proin quam etiam ultricies vitae, vestibulum nulla.
                        </p>
                        <button
                            type="button"
                            class="btn-brand mt-6 w-full py-3 text-sm font-semibold text-white"
                            @click="showHelpModal = false"
                        >
                            Закрыть
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
