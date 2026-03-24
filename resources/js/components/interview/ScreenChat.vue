<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';

const props = defineProps({
    firstName: { type: String, default: '' },
    answerTimeLabel: { type: String, default: '2 минуты' },
    hasMicrophoneAccess: { type: Boolean, default: false },
    showInstructionsMessage: { type: Boolean, default: false },
    interviewStarted: { type: Boolean, default: false },
    questions: { type: Array, default: () => [] },
    companyQuestions: { type: Array, default: () => [] },
    currentQuestionIndex: { type: Number, default: 0 },
    interviewCompleted: { type: Boolean, default: false },
    submittedAnswers: { type: Object, default: () => ({}) },
    remainingSeconds: { type: Number, default: 0 },
    formatTimer: { type: Function, default: () => '' },
    completionMessage: { type: String, default: '' },
    transcribing: { type: Boolean, default: false },
    phraseCompleted: { type: Boolean, default: false },
    phraseResult: { type: String, default: '' },
    microphoneStatus: { type: String, default: '' },
    microphoneStatusError: { type: Boolean, default: false },
    isRecordingPhrase: { type: Boolean, default: false },
    submitting: { type: Boolean, default: false },
    skipSubmitting: { type: Boolean, default: false },
    isRecordingAnswer: { type: Boolean, default: false },
    answerStatus: { type: String, default: '' },
    answerStatusError: { type: Boolean, default: false },
    showRecordHint: { type: Boolean, default: false },
    highlightRecordButton: { type: Boolean, default: false },
    recordingSupported: { type: Boolean, default: true },
    feedbackRating: { type: Number, default: null },
    feedbackSubmitting: { type: Boolean, default: false },
    feedbackStatus: { type: String, default: '' },
    feedbackStatusError: { type: Boolean, default: false },
    customQuestion: { type: String, default: '' },
    customQuestionSubmitting: { type: Boolean, default: false },
    customQuestionStatus: { type: String, default: '' },
    customQuestionStatusError: { type: Boolean, default: false },
});
const emit = defineEmits([
    'request-microphone',
    'toggle-phrase-record',
    'continue',
    'start',
    'record-toggle',
    'skip-answer',
    'feedback-select',
    'custom-question-submit',
]);

function formatMessageTime() {
    const d = new Date();
    return [d.getHours(), d.getMinutes(), d.getSeconds()]
        .map((n) => String(n).padStart(2, '0'))
        .join(':');
}

const welcomeTexts = computed(() => [
    'Привет 👋',
    'Я - {Имя}, твой виртуальный интервьюер.',
    `Я запрограммирован оценить твои знания с помощью ряда фундаментальных вопросов. Для ответа на каждый вопрос у тебя будет ${props.answerTimeLabel}.`,
    'Чтобы записать ответ, нужно использовать микрофон. Давай проверим, что он включен и работает.',
]);

/** Сообщения бота по порядку: welcome — текст приветствия, sentence — блок «Прочтите предложение». time — чч:мм:сс по локальному времени пользователя. */
const botBlocks = ref([]);
let timeouts = [];

const userInitial = computed(() => {
    const name = (props.firstName || '').trim();
    return name ? name[0].toUpperCase() : 'Вы'[0];
});

const phraseResultTime = ref('');
const phraseReplyTime = ref('');

watch(() => props.phraseResult, (v) => {
    if (v) phraseResultTime.value = formatMessageTime();
}, { immediate: true });

watch([() => props.phraseCompleted, () => props.phraseResult], ([completed, result]) => {
    if (completed && result) phraseReplyTime.value = formatMessageTime();
}, { immediate: true });

const completionMessageTime = ref('');
watch(() => props.interviewCompleted, (completed) => {
    if (completed) completionMessageTime.value = formatMessageTime();
}, { immediate: true });

const continueLoading = ref(false);
const hoveredFeedbackStar = ref(null);
const showAllCompanyQuestions = ref(false);
const selectedCompanyQuestionMessages = ref([]);
const showCustomQuestionForm = ref(false);
const customQuestionText = ref('');

function handleContinueClick() {
    continueLoading.value = true;
    emit('continue');
    setTimeout(() => {
        continueLoading.value = false;
    }, 400);
}

function isFeedbackStarActive(starValue) {
    if (Number.isInteger(hoveredFeedbackStar.value)) {
        return hoveredFeedbackStar.value >= starValue;
    }

    return Number.isInteger(props.feedbackRating) && props.feedbackRating >= starValue;
}

const normalizedCompanyQuestions = computed(() => {
    if (!Array.isArray(props.companyQuestions)) {
        return [];
    }

    return props.companyQuestions.filter((item) => {
        return item && typeof item.question === 'string' && typeof item.answer === 'string';
    });
});

const visibleCompanyQuestions = computed(() => {
    if (showAllCompanyQuestions.value) {
        return normalizedCompanyQuestions.value;
    }

    return normalizedCompanyQuestions.value.slice(0, 3);
});

function handleCustomQuestionSubmit() {
    const text = customQuestionText.value.trim();
    if (!text) {
        return;
    }
    emit('custom-question-submit', text);
}

function handleCompanyQuestionSelect(question) {
    selectedCompanyQuestionMessages.value = [
        ...selectedCompanyQuestionMessages.value,
        {
            key: `${question.id}-${Date.now()}-${selectedCompanyQuestionMessages.value.length}`,
            question: question.question,
            answer: question.answer,
        },
    ];
}

const visibleQuestions = () => {
    if (!props.interviewStarted || !Array.isArray(props.questions)) return [];
    if (props.interviewCompleted) return props.questions;
    const idx = Math.max(props.currentQuestionIndex, 0);
    return props.questions.slice(0, Math.min(idx + 1, props.questions.length));
};

function scrollToCurrent() {
    nextTick(() => {
        const el = document.getElementById('current-question-card') || document.getElementById('interview-completed-card');
        el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

watch(
    () => [props.interviewStarted, props.currentQuestionIndex, props.interviewCompleted],
    scrollToCurrent,
    { immediate: true },
);

onMounted(() => {
    const add = (item) => botBlocks.value = [...botBlocks.value, { ...item, time: formatMessageTime() }];
    add({ type: 'welcome', text: welcomeTexts.value[0] });
    timeouts.push(setTimeout(() => add({ type: 'welcome', text: welcomeTexts.value[1] }), 1000));
    timeouts.push(setTimeout(() => add({ type: 'welcome', text: welcomeTexts.value[2] }), 1000 + 1000));
    timeouts.push(setTimeout(() => add({ type: 'welcome', text: welcomeTexts.value[3] }), 1000 + 1000 + 1500));
    timeouts.push(setTimeout(() => add({ type: 'sentence' }), 1000 + 1000 + 1500 + 1000));
});

onUnmounted(() => {
    timeouts.forEach(clearTimeout);
});
</script>

<template>
    <section class="min-h-[78vh] font-sans" :class="{ 'pb-44': interviewStarted && !interviewCompleted }">
        <div class="mx-auto max-w-[760px] space-y-5 pb-40">
            <!-- Сообщения бота по таймлайну (без TransitionGroup) -->
            <div
                v-for="(block, index) in botBlocks"
                :key="index"
                class="flex items-start gap-3"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg font-semibold text-white"
                    style="background-color: var(--color-brand);"
                    aria-hidden="true"
                >
                    L
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <div class="flex items-start gap-2">
                        <div
                            v-if="block.type === 'welcome'"
                            class="chat-bubble min-w-0 max-w-[520px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-base text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]"
                        >
                            {{ block.text }}
                        </div>
                        <div
                            v-else-if="block.type === 'sentence'"
                            class="chat-bubble min-w-0 rounded-2xl border border-[#E6E6EF] bg-white px-6 py-5 shadow-[0_10px_32px_rgba(93,103,166,0.12)]"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#636985]">Прочтите предложение:</p>
                            <p class="mt-2 text-lg font-medium text-[#232742]">«Хотите понять других - пристальнее смотрите в самого себя.»</p>
                            <p
                                class="mt-3 text-base text-[#4f556f]"
                                :class="microphoneStatusError ? 'text-red-600' : ''"
                            >
                                {{ microphoneStatus || 'Разрешите доступ к микрофону и проверьте запись.' }}
                            </p>

                            <div v-if="!phraseCompleted" class="mt-4 flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    :disabled="phraseCompleted"
                                    class="inline-flex h-12 cursor-pointer items-center justify-center rounded-2xl border border-[#d8dcf2] bg-white px-6 text-sm font-medium text-[#2f334c] transition-colors duration-200 ease-[ease] hover:border-[#ccd2ed] hover:bg-[#f6f7ff] disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="$emit('request-microphone')"
                                >
                                    Разрешить доступ к микрофону
                                </button>
                                <button
                                    type="button"
                                    :disabled="transcribing || phraseCompleted || (!isRecordingPhrase && !hasMicrophoneAccess)"
                                    :class="[
                                        'inline-flex h-12 cursor-pointer items-center justify-center gap-2 rounded-2xl px-7 text-sm font-semibold text-white transition-colors duration-200 ease-[ease]',
                                        isRecordingPhrase
                                            ? 'bg-red-600 hover:bg-red-700 active:bg-red-800 disabled:cursor-not-allowed disabled:bg-red-400'
                                            : 'btn-brand disabled:cursor-not-allowed disabled:bg-[var(--color-brand-disabled)] disabled:opacity-60',
                                    ]"
                                    @click="$emit('toggle-phrase-record')"
                                >
                                    <span
                                        v-if="transcribing"
                                        class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white border-t-transparent"
                                        aria-hidden="true"
                                    ></span>
                                    <template v-else>
                                        {{ isRecordingPhrase ? 'Остановить запись' : 'Записать фразу' }}
                                    </template>
                                </button>
                            </div>
                        </div>
                    </div>
                    <span v-if="block.time && block.type === 'welcome'" class="text-xs text-[#b4b8cc]">{{ block.time }}</span>
                </div>
            </div>

            <Transition name="chat-message">
                <div v-if="phraseResult !== ''" class="flex items-start justify-end gap-3">
                    <div class="flex min-w-0 max-w-[540px] flex-col items-end gap-1">
                        <div class="chat-bubble min-w-0 max-w-[540px] rounded-2xl rounded-tr-md bg-[var(--color-brand)] px-5 py-4 text-base text-white shadow-[0_10px_32px_rgba(93,103,166,0.22)]">
                            <p>{{ phraseResult || 'Не удалось распознать фразу.' }}</p>
                        </div>
                        <span v-if="phraseResultTime" class="text-xs text-[#b4b8cc]">{{ phraseResultTime }}</span>
                    </div>
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E6E6EF] text-lg font-bold text-black"
                        aria-hidden="true"
                    >
                        {{ userInitial }}
                    </div>
                </div>
            </Transition>

            <Transition name="chat-message">
                <div
                    v-if="phraseCompleted && phraseResult !== ''"
                    class="flex items-start gap-3"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg font-semibold text-white"
                        style="background-color: var(--color-brand);"
                        aria-hidden="true"
                    >
                        L
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <div class="flex items-start gap-2">
                            <div class="chat-bubble min-w-0 max-w-[620px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-base text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                                <p>Все в порядке! Я слышу тебя хорошо. 🤗</p>
                                <button
                                    v-if="!showInstructionsMessage && !interviewStarted"
                                    type="button"
                                    :disabled="continueLoading"
                                    class="btn-brand mt-4 inline-flex h-12 w-full cursor-pointer items-center justify-center gap-2 px-6 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="handleContinueClick"
                                >
                                    <span
                                        v-if="continueLoading"
                                        class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white border-t-transparent"
                                        aria-hidden="true"
                                    ></span>
                                    <template v-else>
                                        Продолжить
                                    </template>
                                </button>
                            </div>
                        </div>
                        <span v-if="phraseReplyTime" class="text-xs text-[#b4b8cc]">{{ phraseReplyTime }}</span>
                    </div>
                </div>
            </Transition>

            <Transition name="chat-message">
                <div
                    v-if="showInstructionsMessage"
                    class="flex items-start gap-3"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg font-semibold text-white"
                        style="background-color: var(--color-brand);"
                        aria-hidden="true"
                    >
                        L
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <div class="chat-bubble min-w-0 max-w-[620px] rounded-2xl rounded-tl-md border border-[#E6E6EF] bg-white px-6 py-5 shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                            <h3 class="text-xl font-bold uppercase tracking-wide text-[#636985]">Как отвечать на вопросы</h3>
                            <p class="mt-3 text-base text-[#555b77]">Время на ответ отсчитывается с момента появления вопроса на экране.</p>

                            <ol class="mt-6 list-decimal space-y-2 pl-5 text-base font-semibold text-[#252a44]">
                                <li>Ознакомьтесь с вопросом</li>
                                <li>Нажмите «Записать ответ»</li>
                                <li>Дайте развернутый ответ, подкрепив его примерами</li>
                                <li>Для сохранения нажмите «Остановить запись»</li>
                            </ol>

                            <button
                                v-if="!interviewStarted"
                                type="button"
                                class="btn-brand mt-6 inline-flex h-12 w-full cursor-pointer items-center justify-center rounded-2xl px-8 text-base font-semibold text-white"
                                @click="$emit('start')"
                            >
                                Поехали!
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Вопросы интервью (дописываются в чат) -->
            <div
                v-for="(question, index) in visibleQuestions()"
                :key="question.id"
                :id="interviewStarted && !interviewCompleted && index === currentQuestionIndex ? 'current-question-card' : undefined"
                class="flex items-start gap-3"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg font-semibold text-white"
                    style="background-color: var(--color-brand);"
                    aria-hidden="true"
                >
                    L
                </div>
                <div class="min-w-0 flex-1 max-w-[620px]">
                    <div
                        v-if="!interviewCompleted && index === currentQuestionIndex"
                        class="chat-bubble rounded-2xl rounded-tl-md bg-white px-6 py-5 text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]"
                    >
                        <p class="text-base font-medium leading-relaxed">{{ question.text }}</p>
                        <p class="mt-4 text-center text-2xl font-medium text-[#2c3150]">{{ formatTimer(remainingSeconds) }}</p>
                    </div>
                    <div
                        v-else
                        class="chat-bubble rounded-2xl rounded-tl-md bg-white px-6 py-4 shadow-[0_10px_32px_rgba(93,103,166,0.12)]"
                    >
                        <p class="text-base font-medium leading-relaxed text-[#6a6f8a]">{{ question.text }}</p>
                        <p class="mt-4 flex items-center justify-center gap-2 text-sm font-medium text-[#289a5f]">
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Сохранено
                        </p>
                    </div>
                </div>
            </div>

            <!-- Сообщение о завершении интервью -->
            <div
                v-if="interviewStarted && interviewCompleted"
                id="interview-completed-card"
                class="flex items-start gap-3"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg font-semibold text-white"
                    style="background-color: var(--color-brand);"
                    aria-hidden="true"
                >
                    L
                </div>
                <div class="min-w-0 flex-1 max-w-[620px] flex flex-col gap-1">
                    <div class="chat-bubble rounded-2xl rounded-tl-md bg-white px-6 py-5 text-base text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                        <p>{{ completionMessage }}</p>
                        <div class="mt-5 rounded-2xl border border-[#eceffd] bg-[#f8f9ff] p-4">
                            <p class="text-sm font-semibold text-[#2f344d]">Оцените, пожалуйста, как прошло интервью</p>
                            <div class="mt-3 flex items-center gap-2">
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    :disabled="feedbackSubmitting"
                                    :aria-label="`Поставить ${star} звезд`"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-transparent transition-colors hover:text-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    :class="isFeedbackStarActive(star) ? 'text-amber-400' : 'text-[#d3d7ea]'"
                                    @mouseenter="hoveredFeedbackStar = star"
                                    @mouseleave="hoveredFeedbackStar = null"
                                    @click="emit('feedback-select', star)"
                                >
                                    <svg class="h-7 w-7 fill-current" viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M10 1.667l2.573 5.213 5.754.836-4.164 4.059.983 5.731L10 14.803l-5.146 2.703.983-5.731L1.673 7.716l5.754-.836L10 1.667z" />
                                    </svg>
                                </button>
                            </div>
                            <p
                                v-if="feedbackStatus"
                                class="mt-3 text-sm"
                                :class="feedbackStatusError ? 'text-red-600' : 'text-[#4f556f]'"
                            >
                                {{ feedbackStatus }}
                            </p>
                        </div>
                        <p class="mt-4">
                            Как только ваши ответы будут проанализированы, вы сможете получить приглашение на следующий этап интервью.
                        </p>
                        <p class="mt-2">
                            Эту страницу можно закрыть.
                        </p>
                    </div>
                    <span v-if="completionMessageTime" class="text-xs text-[#b4b8cc]">{{ completionMessageTime }}</span>
                </div>
            </div>

            <div
                v-if="interviewStarted && interviewCompleted"
                class="space-y-4 pt-2"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg font-semibold text-white"
                        style="background-color: var(--color-brand);"
                        aria-hidden="true"
                    >
                        L
                    </div>
                    <div class="chat-bubble max-w-[620px] rounded-2xl rounded-tl-md bg-white px-6 py-5 text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                        <p>А пока, возможно, есть вопросы о компании.</p>
                        <p v-if="normalizedCompanyQuestions.length > 0" class="mt-1">Выберите вопрос из списка:</p>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-3">
                    <button
                        v-for="question in visibleCompanyQuestions"
                        :key="question.id"
                        type="button"
                        class="inline-flex h-12 cursor-pointer items-center justify-center rounded-2xl border border-[var(--color-brand)] bg-white px-6 text-sm font-semibold text-[var(--color-brand)] transition-colors hover:bg-[var(--color-brand)] hover:text-white"
                        @click="handleCompanyQuestionSelect(question)"
                    >
                        {{ question.question }}
                    </button>
                    <button
                        v-if="normalizedCompanyQuestions.length > 3 && !showAllCompanyQuestions"
                        type="button"
                        class="inline-flex h-12 cursor-pointer items-center justify-center rounded-2xl border border-[var(--color-brand)] bg-white px-6 text-sm font-semibold text-[var(--color-brand)] transition-colors hover:bg-[var(--color-brand)] hover:text-white"
                        @click="showAllCompanyQuestions = true"
                    >
                        Показать все вопросы
                    </button>
                    <button
                        v-if="!customQuestion && !showCustomQuestionForm"
                        type="button"
                        class="inline-flex h-12 cursor-pointer items-center justify-center rounded-2xl border border-dashed border-[var(--color-brand)] bg-white px-6 text-sm font-semibold text-[var(--color-brand)] transition-colors hover:bg-[var(--color-brand)] hover:text-white"
                        @click="showCustomQuestionForm = true"
                    >
                        Другой вопрос
                    </button>
                </div>

                <div v-for="message in selectedCompanyQuestionMessages" :key="message.key" class="space-y-4">
                    <div class="flex items-start justify-end gap-3">
                        <div class="chat-bubble max-w-[620px] rounded-2xl rounded-tr-md bg-[var(--color-brand)] px-6 py-5 text-white shadow-[0_10px_32px_rgba(93,103,166,0.22)]">
                            {{ message.question }}
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg font-semibold text-white"
                            style="background-color: var(--color-brand);"
                            aria-hidden="true"
                        >
                            L
                        </div>
                        <div class="chat-bubble max-w-[620px] rounded-2xl rounded-tl-md bg-white px-6 py-5 text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                            {{ message.answer }}
                        </div>
                    </div>
                </div>

                <div v-if="showCustomQuestionForm && !customQuestion" class="flex items-start justify-end gap-3">
                    <div class="w-full max-w-[620px] rounded-2xl rounded-tr-md bg-white px-6 py-5 shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                        <textarea
                            v-model="customQuestionText"
                            :disabled="customQuestionSubmitting"
                            rows="3"
                            maxlength="1000"
                            placeholder="Напишите ваш вопрос..."
                            class="w-full resize-none rounded-xl border border-[#d8dcf2] bg-[#f8f9ff] px-4 py-3 text-sm text-[#2f344d] placeholder-[#a0a5c0] outline-none transition-colors focus:border-[var(--color-brand)] disabled:opacity-60"
                        ></textarea>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <p
                                v-if="customQuestionStatus"
                                class="text-sm"
                                :class="customQuestionStatusError ? 'text-red-600' : 'text-[#4f556f]'"
                            >
                                {{ customQuestionStatus }}
                            </p>
                            <div class="ml-auto flex gap-2">
                                <button
                                    type="button"
                                    :disabled="customQuestionSubmitting"
                                    class="inline-flex h-10 cursor-pointer items-center justify-center rounded-xl border border-[#d8dcf2] bg-white px-5 text-sm font-medium text-[#2f334c] transition-colors hover:bg-[#f6f7ff] disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="showCustomQuestionForm = false; customQuestionText = '';"
                                >
                                    Отмена
                                </button>
                                <button
                                    type="button"
                                    :disabled="customQuestionSubmitting || !customQuestionText.trim()"
                                    class="btn-brand inline-flex h-10 cursor-pointer items-center justify-center gap-2 rounded-xl px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="handleCustomQuestionSubmit"
                                >
                                    <span
                                        v-if="customQuestionSubmitting"
                                        class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white border-t-transparent"
                                        aria-hidden="true"
                                    ></span>
                                    <template v-else>Отправить</template>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="customQuestion" class="space-y-4">
                    <div class="flex items-start justify-end gap-3">
                        <div class="chat-bubble max-w-[620px] rounded-2xl rounded-tr-md bg-[var(--color-brand)] px-6 py-5 text-white shadow-[0_10px_32px_rgba(93,103,166,0.22)]">
                            {{ customQuestion }}
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg font-semibold text-white"
                            style="background-color: var(--color-brand);"
                            aria-hidden="true"
                        >
                            L
                        </div>
                        <div class="chat-bubble max-w-[620px] rounded-2xl rounded-tl-md bg-white px-6 py-5 text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                            Спасибо! Ваш вопрос передан рекрутеру. Вам ответят в ближайшее время.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Панель записи ответа (только в фазе интервью, до завершения) -->
        <div
            v-show="interviewStarted && !interviewCompleted"
            class="fixed inset-x-0 bottom-8 z-20 py-4 backdrop-blur-[4px]"
        >
            <div class="mx-auto flex max-w-[1080px] flex-col items-center justify-center gap-4 px-10">
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <button
                        type="button"
                        :disabled="transcribing || submitting || isRecordingAnswer || currentQuestionIndex >= questions.length"
                        class="inline-flex h-12 cursor-pointer items-center justify-center gap-2 rounded-2xl border border-[#d8dcf2] bg-white px-6 text-sm font-medium text-[#2f334c] transition-colors duration-200 ease-[ease] hover:border-[#ccd2ed] hover:bg-[#f6f7ff] disabled:cursor-not-allowed disabled:opacity-60"
                        @click="$emit('skip-answer')"
                    >
                        <span
                            v-if="skipSubmitting"
                            class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-[#2f334c] border-t-transparent"
                            aria-hidden="true"
                        ></span>
                        <template v-else>
                            Не знаю ответ
                        </template>
                    </button>
                    <button
                        type="button"
                        :disabled="transcribing || submitting || interviewCompleted || currentQuestionIndex >= questions.length"
                        :class="[
                            'inline-flex h-12 cursor-pointer items-center justify-center gap-2 rounded-2xl px-8 text-sm font-semibold text-white transition-colors duration-200 ease-[ease]',
                            isRecordingAnswer
                                ? 'bg-red-600 hover:bg-red-700 active:bg-red-800 disabled:cursor-not-allowed disabled:bg-red-400'
                                : 'btn-brand disabled:cursor-not-allowed disabled:bg-[var(--color-brand-disabled)] disabled:opacity-60',
                            highlightRecordButton && !isRecordingAnswer
                                ? 'ring-4 ring-[var(--color-brand)]/25 ring-offset-2 ring-offset-white motion-safe:animate-pulse'
                                : '',
                        ]"
                        @click="$emit('record-toggle')"
                    >
                        <span
                            v-if="transcribing || (submitting && !skipSubmitting)"
                            class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white border-t-transparent"
                            aria-hidden="true"
                        ></span>
                        <template v-else>
                            {{ isRecordingAnswer ? 'Остановить запись' : 'Записать ответ' }}
                        </template>
                    </button>
                </div>
                <p
                    v-if="showRecordHint && !isRecordingAnswer && !transcribing && !submitting"
                    class="text-center text-sm text-[#4f556f]"
                >
                    Нажмите «Записать ответ», чтобы отправить ответ на текущий вопрос.
                </p>
                <p
                    v-show="!recordingSupported"
                    class="text-center text-xs text-amber-700"
                >
                    Запись звука не поддерживается в этом браузере. Пожалуйста, используйте Chrome или Safari.
                </p>
                <p
                    v-if="answerStatus"
                    class="text-center text-sm"
                    :class="answerStatusError ? 'text-red-600' : 'text-[#4f556f]'"
                >
                    {{ answerStatus }}
                </p>
            </div>
        </div>
    </section>
</template>
