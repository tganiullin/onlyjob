<script setup>
defineProps({
    firstName: { type: String, default: '' },
    lastName: { type: String, default: '' },
    positionTitle: { type: String, default: '' },
    questionsCount: { type: Number, default: 0 },
});
defineEmits(['start']);
</script>

<template>
    <section class="grid min-h-[78vh] items-center gap-16 lg:grid-cols-[1.2fr_1fr]">
        <div class="space-y-5">
            <p class="text-[22px] text-[#353a56]">Привет, {{ firstName }} {{ lastName }}</p>
            <p class="text-sm text-[#5b617d]">Приглашам вас пройти интервью на позицию:</p>
            <h1 class="text-[58px] font-bold leading-tight text-[#202541]">{{ positionTitle }}</h1>

            <dl class="mt-6 grid max-w-[360px] grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm text-[#5f6481]">
                <dt>Компания</dt>
                <dd class="text-[#2f334d]">Тест</dd>
                <dt>Язык</dt>
                <dd class="text-[#2f334d]">Русский / English</dd>
                <dt>Всего вопросов</dt>
                <dd class="text-[#2f334d]">{{ questionsCount }}</dd>
            </dl>

            <button
                type="button"
                class="mt-6 inline-flex h-14 min-w-[260px] items-center justify-center rounded-full bg-[#1b045f] px-10 text-sm font-semibold text-white shadow-[0_6px_0_rgba(112,102,189,0.55)] transition hover:bg-[#250875]"
                @click="$emit('start')"
            >
                Начать
            </button>
        </div>

        <div class="space-y-4">
            <h2 class="text-3xl font-bold text-[#22263f]">Перед стартом</h2>
            <p class="text-sm text-[#5a607b]">Перед началом интервью убедитесь, что:</p>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-[#d7dcf5] bg-white/70 p-4 text-sm text-[#4e5470]">
                    Вы используете последнюю версию браузера Chrome или Edge
                </div>
                <div class="rounded-2xl border border-[#d7dcf5] bg-white/70 p-4 text-sm text-[#4e5470]">
                    Ваши колонки или наушники работают
                </div>
                <div class="rounded-2xl border border-[#d7dcf5] bg-white/70 p-4 text-sm text-[#4e5470]">
                    Ваш микрофон включен и работает
                </div>
                <div class="rounded-2xl border border-[#d7dcf5] bg-white/70 p-4 text-sm text-[#4e5470]">
                    Вы в тихом помещении и готовы сконцентрироваться
                </div>
            </div>
        </div>
    </section>
</template>
