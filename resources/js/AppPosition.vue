<script setup>
import { ref, reactive } from 'vue';

const props = defineProps({
    submitUrl: { type: String, required: true },
    positionTitle: { type: String, default: '' },
    questionsCount: { type: [Number, String], default: 0 },
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
const submitting = ref(false);

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function setErrors(payload) {
    errors.first_name = payload?.errors?.first_name ?? [];
    errors.last_name = payload?.errors?.last_name ?? [];
    errors.email = payload?.errors?.email ?? [];
    errors.consent = payload?.errors?.consent ?? [];
}

async function onSubmit() {
    setErrors({});
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
                first_name: form.first_name,
                last_name: form.last_name,
                email: form.email,
                consent: form.consent ? '1' : '',
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422) {
                setErrors(data);
                return;
            }
            errors.email = [data?.message ?? 'Произошла ошибка. Попробуйте ещё раз.'];
            return;
        }

        if (data.redirect) {
            window.location.href = data.redirect;
            return;
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-[minmax(280px,0.9fr)_minmax(640px,1fr)]">
        <aside class="bg-[#dde2f9] px-10 py-10">
            <div class="mx-auto w-full max-w-[360px]">
                <div class="text-[34px] font-black tracking-[0.22em] text-[#1f2440]">LARAVEL</div>

                <div class="mt-32 space-y-4">
                    <p class="text-[20px] leading-tight text-[#2b2f45]">Привет 👋</p>
                    <p class="text-sm text-[#4b4f67]">Приглашам вас пройти интервью на позицию:</p>
                    <h1 class="text-[48px] font-bold leading-tight text-[#1f2440]">{{ positionTitle }}</h1>
                </div>

                <dl class="mt-8 grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm text-[#5c6076]">
                    <dt class="opacity-80">Компания</dt>
                    <dd class="text-[#2f334c]">Тест</dd>

                    <dt class="opacity-80">Язык</dt>
                    <dd class="text-[#2f334c]">Русский / English</dd>

                    <dt class="opacity-80">Всего вопросов</dt>
                    <dd class="text-[#2f334c]">{{ questionsCount }}</dd>
                </dl>
            </div>
        </aside>

        <main class="px-6 py-10 sm:px-10">
            <div class="mx-auto flex min-h-full w-full max-w-[460px] items-center">
                <section class="w-full space-y-6 rounded-3xl bg-white/70 p-8 shadow-[0_20px_55px_rgba(80,94,170,0.14)] backdrop-blur-sm">
                    <header class="space-y-3 text-center">
                        <h2 class="text-[36px] font-bold leading-tight text-[#252a45]">
                            Чтобы начать собеседование заполните форму ниже
                        </h2>
                    </header>

                    <form class="space-y-4" @submit.prevent="onSubmit">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="first_name" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[#636985]">Имя</label>
                                <input
                                    id="first_name"
                                    v-model="form.first_name"
                                    type="text"
                                    class="h-12 w-full rounded-xl border border-[#d8dcf2] bg-white px-3 text-sm text-[#1f2440] outline-none transition focus:border-[#8b92f0]"
                                    required
                                >
                                <p v-for="msg in errors.first_name" :key="msg" class="mt-1 text-xs text-red-600">{{ msg }}</p>
                            </div>

                            <div>
                                <label for="last_name" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[#636985]">Фамилия</label>
                                <input
                                    id="last_name"
                                    v-model="form.last_name"
                                    type="text"
                                    class="h-12 w-full rounded-xl border border-[#d8dcf2] bg-white px-3 text-sm text-[#1f2440] outline-none transition focus:border-[#8b92f0]"
                                    required
                                >
                                <p v-for="msg in errors.last_name" :key="msg" class="mt-1 text-xs text-red-600">{{ msg }}</p>
                            </div>
                        </div>

                        <div>
                            <label for="email" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[#636985]">Электронная почта</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="h-12 w-full rounded-xl border border-[#d8dcf2] bg-white px-3 text-sm text-[#1f2440] outline-none transition focus:border-[#8b92f0]"
                                required
                            >
                            <p v-for="msg in errors.email" :key="msg" class="mt-1 text-xs text-red-600">{{ msg }}</p>
                        </div>

                        <label class="flex cursor-pointer items-start gap-2 rounded-xl border border-[#e0e4f5] bg-white px-3 py-2.5 text-sm text-[#555a73]">
                            <input
                                v-model="form.consent"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-[#ccd2ed]"
                            >
                            <span>Я даю согласие на обработку персональных данных и принимаю Политику Конфиденциальности</span>
                        </label>
                        <p v-for="msg in errors.consent" :key="msg" class="text-xs text-red-600">{{ msg }}</p>

                        <button
                            type="submit"
                            :disabled="submitting"
                            class="mt-3 inline-flex h-12 w-full items-center justify-center rounded-full bg-[#d8d4fa] px-6 text-sm font-semibold text-[#3b3f66] transition hover:bg-[#cbc4f5] disabled:opacity-70"
                        >
                            {{ submitting ? 'Отправка…' : 'Продолжить' }}
                        </button>
                    </form>
                </section>
            </div>
        </main>
    </div>
</template>
