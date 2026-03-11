<script setup>
defineEmits(['start']);
</script>

<template>
    <div class="fixed inset-0 z-40 flex items-center justify-center bg-[#1f2440]/45 px-4">
        <div class="w-full max-w-[860px] rounded-[30px] bg-white p-8 shadow-[0_32px_80px_rgba(44,50,88,0.35)]">
            <h3 class="text-[32px] font-bold uppercase tracking-wide text-[#7a43c9]">Как отвечать на вопросы</h3>
            <p class="mt-3 text-sm text-[#555b77]">Время на ответ отсчитывается с момента появления вопроса на экране.</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-[#d8dcf5] bg-[#f6f7ff] p-4 text-base font-semibold text-[#252a44]">
                    1. Внимательно прослушайте вопрос
                </div>
                <div class="rounded-2xl border border-[#d8dcf5] bg-[#f6f7ff] p-4 text-base font-semibold text-[#252a44]">
                    2. Нажмите «Записать ответ»
                </div>
                <div class="rounded-2xl border border-[#d8dcf5] bg-[#f6f7ff] p-4 text-base font-semibold text-[#252a44]">
                    3. Дайте развернутый ответ, подкрепив его примерами
                </div>
                <div class="rounded-2xl border border-[#d8dcf5] bg-[#f6f7ff] p-4 text-base font-semibold text-[#252a44]">
                    4. Для сохранения нажмите «Остановить запись»
                </div>
            </div>

            <button
                type="button"
                class="mt-8 inline-flex h-14 w-full items-center justify-center rounded-full bg-[#6d76e8] px-8 text-base font-semibold text-white transition hover:bg-[#5c66dd]"
                @click="$emit('start')"
            >
                Поехали!
            </button>
        </div>
    </div>
</template>
