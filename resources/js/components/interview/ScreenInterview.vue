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

const visibleCount = ref(1);

function getAnswer(question) {
    const id = question?.id;
    if (id == null) return '';
    return props.submittedAnswers[id] ?? '';
}

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
    <section class="min-h-[78vh] pb-44">
        <div class="mx-auto max-w-[760px] space-y-10">
            <template v-for="(question, index) in visibleQuestions()" :key="question.id">
                <section
                    v-if="!interviewCompleted && index === currentQuestionIndex"
                    id="current-question-card"
                    class="space-y-3"
                >
                    <div class="flex items-center gap-2 text-xs text-[#72789c]">
                        <span class="h-[1px] flex-1 bg-[#d5daf3]"></span>
                        <span>Вопрос {{ index + 1 }} из {{ questions.length }}</span>
                    </div>
                    <article class="rounded-2xl bg-white px-8 py-10 shadow-[0_12px_36px_rgba(93,103,166,0.13)]">
                        <p class="text-center text-[28px] font-medium leading-tight text-[#202541]">{{ question.text }}</p>
                        <p class="mt-8 text-center text-2xl font-medium text-[#2c3150]">{{ formatTimer(remainingSeconds) }}</p>
                    </article>
                </section>

                <section v-else class="space-y-3">
                    <div class="flex items-center gap-2 text-xs text-[#72789c]">
                        <span class="h-[1px] flex-1 bg-[#d5daf3]"></span>
                        <span>Вопрос {{ index + 1 }} из {{ questions.length }}</span>
                    </div>
                    <article class="rounded-2xl bg-white px-8 py-9 shadow-[0_12px_36px_rgba(93,103,166,0.13)]">
                        <p class="text-center text-xl font-medium leading-relaxed text-[#222744]">{{ question.text }}</p>
                        <p class="mt-6 text-center text-sm font-medium text-[#289a5f]">✓ Сохранено</p>
                        <p v-if="getAnswer(question)" class="mt-4 text-center text-xs text-[#7d84a5]">{{ getAnswer(question) }}</p>
                    </article>
                </section>
            </template>

            <section v-if="interviewCompleted" id="interview-completed-card" class="space-y-3">
                <article class="rounded-2xl rounded-tl-md bg-white px-8 py-8 shadow-[0_12px_36px_rgba(93,103,166,0.13)]">
                    <p class="text-center text-sm text-[#2f344d]">{{ completionMessage }}</p>
                </article>
            </section>
        </div>

        <div class="fixed inset-x-0 bottom-8 z-20 pl-[270px]">
            <div class="mx-auto flex max-w-[1080px] flex-col items-center justify-center gap-6 px-10">
                <div class="flex items-center gap-6">
                    <button
                        type="button"
                        class="inline-flex h-12 min-w-[140px] items-center justify-center rounded-full border border-transparent px-6 text-sm text-[#2e3350] transition hover:bg-white/60 disabled:opacity-40 disabled:cursor-not-allowed"
                        :disabled="transcribing || submitting || isRecordingAnswer || interviewCompleted || currentQuestionIndex >= questions.length"
                        @click="emit('skip-answer')"
                    >
                        Не знаю ответ
                    </button>

                    <button
                        type="button"
                        :disabled="transcribing || submitting || isRecordingAnswer || interviewCompleted || currentQuestionIndex >= questions.length"
                        :class="[
                            'inline-flex h-14 min-w-[280px] items-center justify-center rounded-full px-8 text-sm font-semibold text-white transition',
                            isRecordingAnswer
                                ? 'bg-[#eb1f3a] shadow-[0_6px_0_rgba(160,28,45,0.45)] hover:bg-[#d61731]'
                                : 'bg-[#1b045f] shadow-[0_6px_0_rgba(112,102,189,0.55)] hover:bg-[#250875]',
                        ]"
                        @click="emit('record-toggle')"
                    >
                        {{ isRecordingAnswer ? 'Остановить запись' : 'Записать ответ' }}
                    </button>
                </div>

                <p
                    class="text-center text-sm"
                    :class="answerStatusError ? 'text-red-600' : 'text-[#545a78]'"
                >
                    {{ answerStatus }}
                </p>
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
