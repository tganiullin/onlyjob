<script setup>
import { ref, reactive, computed } from 'vue';

const PREP_SECONDS = 120;

const props = defineProps({
    submitUrl: { type: String, required: true },
    positionTitle: { type: String, default: '' },
    questionsCount: { type: [Number, String], default: 0 },
    answerTimeSeconds: { type: Number, default: 120 },
    policyUrl: { type: String, default: '#' },
});

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    consent: false,
});
const errors = reactive({
    first_name: [],
    last_name: [],
    email: [],
    consent: [],
});
const submitted = reactive({
    first_name: false,
    last_name: false,
    email: false,
    consent: false,
});
const submitting = ref(false);

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

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function validate() {
    const e = { first_name: [], last_name: [], email: [], consent: [] };
    if (!form.first_name.trim()) e.first_name.push('Укажите имя.');
    if (!form.last_name.trim()) e.last_name.push('Укажите фамилию.');
    if (!form.email.trim()) e.email.push('Укажите электронную почту.');
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email.push('Укажите корректный адрес электронной почты.');
    if (!form.consent) e.consent.push('Необходимо дать согласие на обработку персональных данных.');
    Object.assign(errors, e);
    submitted.first_name = true;
    submitted.last_name = true;
    submitted.email = true;
    submitted.consent = true;
    return e.first_name.length + e.last_name.length + e.email.length + e.consent.length === 0;
}

function setErrors(payload) {
    errors.first_name = payload?.errors?.first_name ?? [];
    errors.last_name = payload?.errors?.last_name ?? [];
    errors.email = payload?.errors?.email ?? [];
    errors.consent = payload?.errors?.consent ?? [];
}

async function onSubmit() {
    setErrors({});
    if (!validate()) {
        submitting.value = false;
        return;
    }
    submitting.value = true;

    try {
        const csrf = getCsrfToken();
        const response = await fetch(props.submitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                first_name: form.first_name.trim(),
                last_name: form.last_name.trim(),
                email: form.email.trim(),
                consent: form.consent ? '1' : '',
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422) setErrors(data);
            else errors.email = [data?.message ?? 'Произошла ошибка. Попробуйте ещё раз.'];
            return;
        }

        if (data.redirect) {
            window.location.href = data.redirect;
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-[minmax(280px,0.9fr)_minmax(640px,1fr)]">
        <aside class="bg-[#eff3f8] px-10 py-10">
            <div class="mx-auto w-full max-w-[480px]">
                <div class="text-[34px] font-black tracking-[0.22em] text-[#1f2440]">AYA</div>

                <div class="mt-32 space-y-4">
                    <p class="text-3xl font-medium leading-tight text-[#2b2f45]">Привет 👋</p>
                    <p class="text-xl font-light text-[#4b4f67]">Приглашаем вас пройти интервью на позицию:</p>
                    <h1 class="mt-8 text-3xl font-bold leading-tight text-[#1f2440]">{{ positionTitle }}</h1>
                </div>

                <dl class="mt-8 grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm text-[#5c6076]">
<!--                    <dt class="opacity-80">Компания</dt>-->
<!--                    <dd class="text-[#2f334c]">Тест</dd>-->

                    <dt class="opacity-80 font-light">Язык</dt>
                    <dd class="text-[#2f334c] font-medium">Русский</dd>

                    <dt class="opacity-80 font-light">Вопросов</dt>
                    <dd class="text-[#2f334c] font-medium">{{ questionsCount }}</dd>

                    <dt class="opacity-80 font-light">Время</dt>
                    <dd class="text-[#2f334c] font-medium">{{ totalTimeLabel }}</dd>
                </dl>
            </div>
        </aside>

        <main class="px-6 py-10 sm:px-10 bg-white">
            <div class="mx-auto flex min-h-full w-full max-w-[540px] items-center">
                <section class="w-full space-y-6 rounded-3xl bg-white p-8 border border-[#E6E6EF]">
                    <header class="space-y-3 text-left">
                        <h2 class="text-xl font-bold leading-tight text-[#252a45]">
                            Заполните форму, чтобы начать собеседование
                        </h2>
                    </header>

                    <form class="space-y-4" @submit.prevent="onSubmit" novalidate>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="first_name" class="mb-1.5 block text-sm font-medium text-[#636985]">Имя</label>
                                <input
                                    id="first_name"
                                    v-model="form.first_name"
                                    type="text"
                                    autocomplete="given-name"
                                    class="input-field"
                                    :class="{ 'input-field--invalid': errors.first_name.length > 0 }"
                                    @blur="submitted.first_name = true"
                                >
                                <p v-for="msg in errors.first_name" :key="msg" class="text-xs text-red-600">{{ msg }}</p>
                            </div>

                            <div>
                                <label for="last_name" class="mb-1.5 block text-sm font-medium text-[#636985]">Фамилия</label>
                                <input
                                    id="last_name"
                                    v-model="form.last_name"
                                    type="text"
                                    autocomplete="family-name"
                                    class="input-field"
                                    :class="{ 'input-field--invalid': errors.last_name.length > 0 }"
                                    @blur="submitted.last_name = true"
                                >
                                <p v-for="msg in errors.last_name" :key="msg" class="text-xs text-red-600">{{ msg }}</p>
                            </div>
                        </div>

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-[#636985]">Электронная почта</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                class="input-field"
                                :class="{ 'input-field--invalid': errors.email.length > 0 }"
                                @blur="submitted.email = true"
                            >
                            <p v-for="msg in errors.email" :key="msg" class="text-xs text-red-600">{{ msg }}</p>
                        </div>

                        <div>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#e0e4f5] bg-white px-3 py-2.5 text-sm text-[#555a73]">
                                <input
                                    v-model="form.consent"
                                    type="checkbox"
                                    class="sr-only"
                                    @change="submitted.consent = true"
                                >
                                <span
                                    class="checkbox-consent mt-0.5"
                                    :class="{ 'checkbox-consent--checked': form.consent }"
                                >
                                    <svg v-show="form.consent" class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 12 12" stroke-width="2">
                                        <path d="M2 6l3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span>Я даю согласие на обработку персональных данных и принимаю <a :href="policyUrl" target="_blank" rel="noopener noreferrer" class="underline hover:opacity-80" @click.stop>Политику Конфиденциальности</a></span>
                            </label>
                            <p v-for="msg in errors.consent" :key="msg" class="text-xs text-red-600">{{ msg }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="submitting"
                            class="btn-brand mt-3 inline-flex h-12 w-full cursor-pointer items-center justify-center px-6 text-sm font-semibold text-white"
                        >
                            {{ submitting ? 'Отправка…' : 'Продолжить' }}
                        </button>
                    </form>
                </section>
            </div>
        </main>
    </div>
</template>
