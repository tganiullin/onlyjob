<script setup>
import { ref, computed, reactive } from 'vue';
import ScreenStart from './components/interview/ScreenStart.vue';
import ScreenChat from './components/interview/ScreenChat.vue';
import ScreenInterview from './components/interview/ScreenInterview.vue';
import InstructionsModal from './components/interview/InstructionsModal.vue';
import { useQuestionTimer } from './composables/useQuestionTimer.js';
import { useRecording } from './composables/useRecording.js';
import { useInterviewApi } from './composables/useInterviewApi.js';

const props = defineProps({
    questions: { type: Array, default: () => [] },
    answerEndpointTemplate: { type: String, default: '' },
    transcribeEndpoint: { type: String, default: '' },
    answerTimeSeconds: { type: Number, default: 120 },
    interviewCompleted: { type: Boolean, default: false },
    completionMessage: { type: String, default: 'Спасибо! Вы завершили интервью. Ваши ответы успешно сохранены.' },
    firstName: { type: String, default: '' },
    lastName: { type: String, default: '' },
    positionTitle: { type: String, default: '' },
    answerTimeLabel: { type: String, default: '2 минуты' },
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
const showInstructionsModal = ref(false);

const phraseCompleted = ref(false);
const phraseResult = ref('');
const microphoneStatus = ref('');
const microphoneStatusError = ref(false);
const transcribing = ref(false);
const submitting = ref(false);
const answerStatus = ref(completed.value ? props.completionMessage : '');
const answerStatusError = ref(false);

const api = useInterviewApi({
    transcribeEndpoint: props.transcribeEndpoint,
    answerEndpointTemplate: props.answerEndpointTemplate,
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

const interviewFinished = computed(() => completed.value || currentIndex.value >= questions.value.length);

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
        onStop: async (blob, mode) => {
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
    showInstructionsModal.value = true;
}

function handleInstructionsStart() {
    showInstructionsModal.value = false;
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
            answerStatus.value = 'Идет запись ответа...';
            answerStatusError.value = false;
        },
        onStop: async (blob) => {
            transcribing.value = true;
            answerStatus.value = 'Распознаю ответ...';
            answerStatusError.value = false;
            try {
                const text = await api.transcribe(blob);
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
        },
    });
}

function handleSkipAnswer() {
    submitAnswer('Не знаю ответ');
}
</script>

<template>
    <div class="relative min-h-screen overflow-hidden">
        <aside class="fixed inset-y-0 left-0 w-[270px] bg-[#dce2f8] px-12 py-10">
            <div class="text-[34px] font-black tracking-[0.22em] text-[#1f2440]">AYA</div>
        </aside>

        <main class="min-h-screen pl-[270px]">
            <a href="#" class="absolute right-10 top-10 text-sm text-[#61678b] hover:text-[#464c72]">ⓘ Помощь</a>

            <div class="mx-auto w-full max-w-[1080px] px-10 py-12">
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
                    v-show="currentScreen === 'chat'"
                    :answer-time-label="answerTimeLabel"
                    :transcribing="transcribing"
                    :phrase-completed="phraseCompleted"
                    :phrase-result="phraseResult"
                    :microphone-status="microphoneStatus"
                    :microphone-status-error="microphoneStatusError"
                    :is-recording-phrase="isRecordingPhrase"
                    @request-microphone="handleRequestMicrophone"
                    @toggle-phrase-record="handleTogglePhraseRecord"
                    @continue="handleChatContinue"
                />

                <ScreenInterview
                    v-show="currentScreen === 'interview'"
                    :questions="questions"
                    :current-question-index="currentIndex"
                    :interview-completed="completed"
                    :submitted-answers="submittedAnswers"
                    :remaining-seconds="remainingSeconds"
                    :format-timer="formatTimer"
                    :completion-message="completionMessage"
                    :transcribing="transcribing"
                    :submitting="submitting"
                    :is-recording-answer="isRecordingAnswer"
                    :answer-status="answerStatus"
                    :answer-status-error="answerStatusError"
                    :recording-supported="recordingSupported"
                    @record-toggle="handleRecordToggle"
                    @skip-answer="handleSkipAnswer"
                />
            </div>
        </main>

        <InstructionsModal
            v-show="showInstructionsModal"
            @start="handleInstructionsStart"
        />
    </div>
</template>
