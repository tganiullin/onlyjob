<script setup>
import { computed } from 'vue';

const PREP_SECONDS = 120;

const props = defineProps({
    firstName: { type: String, default: '' },
    lastName: { type: String, default: '' },
    positionTitle: { type: String, default: '' },
    questionsCount: { type: Number, default: 0 },
    answerTimeSeconds: { type: Number, default: 120 },
});
defineEmits(['start']);

const totalTimeLabel = computed(() => {
    const count = Number(props.questionsCount) || 0;
    const secPerQuestion = Math.max(Number(props.answerTimeSeconds) || 120, 1);
    const totalSec = count * secPerQuestion + PREP_SECONDS;
    const totalMin = Math.round(totalSec / 60);
    if (totalMin < 60) return `${totalMin} мин`;
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    return m > 0 ? `${h} ч ${m} мин` : `${h} ч`;
});
</script>

<template>
    <section class="grid min-h-[78vh] items-center gap-16 lg:grid-cols-[1.2fr_1fr]">
        <div class="max-w-[480px] space-y-5">
            <p class="text-3xl font-medium leading-tight text-[#2b2f45]">
                Здравствуйте{{ firstName || lastName ? `, ${firstName} ${lastName}` : '' }} 👋
            </p>
            <p class="text-xl font-normal text-[#4b4f67]">Вы приглашены на собеседование на позицию</p>
            <h1 class="mt-8 text-5xl font-bold leading-none text-[#1f2440]">{{ positionTitle }}</h1>

            <dl class="mt-8 grid grid-cols-[auto_1fr] gap-x-8 gap-y-3 text-sm text-[#5c6076]">
                <dt class="opacity-80 font-light">Язык</dt>
                <dd class="text-[#2f334c] font-medium">Русский</dd>

                <dt class="opacity-80 font-light">Вопросов</dt>
                <dd class="text-[#2f334c] font-medium">{{ questionsCount }}</dd>

                <dt class="opacity-80 font-light">Время</dt>
                <dd class="text-[#2f334c] font-medium">{{ totalTimeLabel }}</dd>
            </dl>

            <button
                type="button"
                class="btn-brand mt-6 inline-flex h-12 min-w-[260px] cursor-pointer items-center justify-center px-10 text-sm font-semibold text-white"
                @click="$emit('start')"
            >
                Начать
            </button>
        </div>

        <div class="space-y-4 rounded-3xl border border-[#E6E6EF] bg-white p-8">
            <h2 class="text-2xl font-bold leading-snug text-[#252a45]">Перед стартом</h2>
            <p class="text-sm text-[#636985]">Перед началом интервью убедитесь, что:</p>

            <div class="flex flex-col gap-3">
                <div class="flex items-start gap-3 rounded-2xl border border-[#e0e4f5] bg-white p-4 text-sm text-[#4e5470]">
                    <span class="mt-0.5 shrink-0 text-brand" aria-hidden="true">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <span>Вы используете актуальную версию браузера</span>
                </div>
                <div class="flex items-start gap-3 rounded-2xl border border-[#e0e4f5] bg-white p-4 text-sm text-[#4e5470]">
                    <span class="mt-0.5 shrink-0 text-brand" aria-hidden="true">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <span>Ваши колонки или наушники включены и работают</span>
                </div>
                <div class="flex items-start gap-3 rounded-2xl border border-[#e0e4f5] bg-white p-4 text-sm text-[#4e5470]">
                    <span class="mt-0.5 shrink-0 text-brand" aria-hidden="true">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <span>Ваш микрофон включен и работает</span>
                </div>
                <div class="flex items-start gap-3 rounded-2xl border border-[#e0e4f5] bg-white p-4 text-sm text-[#4e5470]">
                    <span class="mt-0.5 shrink-0 text-brand" aria-hidden="true">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <span>Вы в тихом помещении и готовы сконцентрироваться на собеседовании</span>
                </div>
            </div>
        </div>
    </section>
</template>
