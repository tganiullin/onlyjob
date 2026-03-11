<script setup>
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
    questions: { type: Array, required: true },
    currentQuestionIndex: { type: Number, default: 0 },
    interviewCompleted: { type: Boolean, default: false },
    submittedAnswers: { type: Object, default: () => ({}) },
    remainingSeconds: { type: Number, default: 0 },
    formatTimer: { type: Function, required: true },
    completionMessage: { type: String, default: '' },
    transcribing: { type: Boolean, default: false },
    submitting: { type: Boolean, default: false },
    isRecordingAnswer: { type: Boolean, default: false },
    answerStatus: { type: String, default: '' },
    answerStatusError: { type: Boolean, default: false },
    recordingSupported: { type: Boolean, default: true },
});
const emit = defineEmits(['record-toggle', 'skip-answer']);

function formatMessageTime() {
    const d = new Date();
    return [d.getHours(), d.getMinutes(), d.getSeconds()]
        .map((n) => String(n).padStart(2, '0'))
        .join(':');
}

const completionMessageTime = ref('');

watch(() => props.interviewCompleted, (completed) => {
    if (completed) completionMessageTime.value = formatMessageTime();
}, { immediate: true });

const visibleQuestions = () => {
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
    () => [props.currentQuestionIndex, props.interviewCompleted],
    scrollToCurrent,
    { immediate: true },
);
</script>

<template>
    <section class="min-h-[78vh] font-sans pb-44">
        <div class="mx-auto max-w-[760px] space-y-5 pb-40">
            <!-- Вопросы как сообщения агента -->
            <div
                v-for="(question, index) in visibleQuestions()"
                :key="question.id"
                :id="!interviewCompleted && index === currentQuestionIndex ? 'current-question-card' : undefined"
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
                            Ответ сохранён
                        </p>
                    </div>
                </div>
            </div>

            <!-- Сообщение о завершении интервью -->
            <div
                v-if="interviewCompleted"
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
                        <p class="mt-4">
                            Как только ваши ответы будут проанализированы, вы сможете получить приглашение на следующий этап интервью.
                        </p>
                        <p class="mt-4">
                            Эту страницу можно закрыть.
                        </p>
                    </div>
                    <span v-if="completionMessageTime" class="text-xs text-[#b4b8cc]">{{ completionMessageTime }}</span>
                </div>
            </div>
        </div>

        <div
            v-show="!interviewCompleted"
            class="fixed inset-x-0 bottom-8 z-20 py-4 backdrop-blur-[4px]"
        >
            <div class="mx-auto flex max-w-[1080px] flex-col items-center justify-center gap-4 px-10">
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <button
                        type="button"
                        :disabled="transcribing || submitting || isRecordingAnswer || interviewCompleted || currentQuestionIndex >= questions.length"
                        class="inline-flex h-12 cursor-pointer items-center justify-center rounded-2xl border border-[#d8dcf2] bg-white px-6 text-sm font-medium text-[#2f334c] transition-colors duration-200 ease-[ease] hover:border-[#ccd2ed] hover:bg-[#f6f7ff] disabled:cursor-not-allowed disabled:opacity-60"
                        @click="emit('skip-answer')"
                    >
                        Не знаю ответ
                    </button>

                    <button
                        type="button"
                        :disabled="transcribing || submitting || interviewCompleted || currentQuestionIndex >= questions.length"
                        :class="[
                            'inline-flex h-12 cursor-pointer items-center justify-center gap-2 rounded-2xl px-8 text-sm font-semibold text-white transition-colors duration-200 ease-[ease]',
                            isRecordingAnswer
                                ? 'bg-red-600 hover:bg-red-700 active:bg-red-800 disabled:cursor-not-allowed disabled:bg-red-400'
                                : 'btn-brand disabled:cursor-not-allowed disabled:bg-[var(--color-brand-disabled)] disabled:opacity-60',
                        ]"
                        @click="emit('record-toggle')"
                    >
                        <span
                            v-if="transcribing"
                            class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white border-t-transparent"
                            aria-hidden="true"
                        ></span>
                        <template v-else>
                            {{ isRecordingAnswer ? 'Остановить запись' : 'Записать ответ' }}
                        </template>
                    </button>
                </div>

                <p
                    v-show="!recordingSupported"
                    class="text-center text-xs text-amber-700"
                >
                    Запись звука не поддерживается в этом браузере. Пожалуйста, используйте Chrome или Safari.
                </p>
            </div>
        </div>
    </section>
</template>
