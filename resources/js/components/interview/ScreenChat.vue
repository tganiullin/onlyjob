<script setup>
defineProps({
    answerTimeLabel: { type: String, default: '2 минуты' },
    transcribing: { type: Boolean, default: false },
    phraseCompleted: { type: Boolean, default: false },
    phraseResult: { type: String, default: '' },
    microphoneStatus: { type: String, default: '' },
    microphoneStatusError: { type: Boolean, default: false },
    isRecordingPhrase: { type: Boolean, default: false },
});
defineEmits(['request-microphone', 'toggle-phrase-record', 'continue']);
</script>

<template>
    <section class="min-h-[78vh]">
        <div class="mx-auto max-w-[760px] space-y-5 pb-40">
            <div class="max-w-[480px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-sm text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                Привет 👋
            </div>
            <div class="max-w-[520px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-sm text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                Я — Laravel, твой виртуальный интервьюер.
            </div>
            <div class="max-w-[620px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-sm text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                Я запрограммирована оценить твои знания с помощью ряда фундаментальных вопросов.
                Для ответа на каждый вопрос у тебя будет {{ answerTimeLabel }}.
            </div>
            <div class="max-w-[620px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-sm text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                Чтобы записать ответ, нужно использовать микрофон. Давай проверим, что он включен и работает.
            </div>

            <div class="rounded-2xl bg-white px-6 py-5 shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#7a43c9]">Прочитай предложение:</p>
                <p class="mt-2 text-lg text-[#232742]">«Хотите понять других - пристальнее смотрите в самого себя.»</p>
                <p
                    class="mt-3 text-sm"
                    :class="microphoneStatusError ? 'text-red-600' : 'text-[#4f556f]'"
                >
                    {{ microphoneStatus || 'Разрешите доступ к микрофону и проверьте запись.' }}
                </p>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-full border border-[#cfd5f5] bg-white px-6 text-sm font-medium text-[#414766] transition hover:bg-[#f6f7ff]"
                        @click="$emit('request-microphone')"
                    >
                        Разрешить микрофон
                    </button>
                    <button
                        type="button"
                        :disabled="transcribing || isRecordingPhrase"
                        :class="[
                            'inline-flex h-11 items-center justify-center rounded-full px-7 text-sm font-semibold text-white transition',
                            isRecordingPhrase
                                ? 'bg-[#eb1f3a] shadow-[0_6px_0_rgba(160,28,45,0.45)] hover:bg-[#d61731]'
                                : 'bg-[#1b045f] shadow-[0_6px_0_rgba(112,102,189,0.55)] hover:bg-[#250875]',
                        ]"
                        @click="$emit('toggle-phrase-record')"
                    >
                        {{ isRecordingPhrase ? 'Остановить запись' : 'Записать фразу' }}
                    </button>
                </div>
            </div>

            <div v-show="phraseResult !== ''" class="flex justify-end">
                <div class="max-w-[540px] rounded-2xl rounded-tr-md bg-[#6d76e8] px-5 py-4 text-sm text-white shadow-[0_10px_32px_rgba(93,103,166,0.22)]">
                    <p>{{ phraseResult || 'Не удалось распознать фразу.' }}</p>
                </div>
            </div>

            <div
                v-show="phraseCompleted && phraseResult !== ''"
                class="max-w-[620px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-sm text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]"
            >
                Все в порядке! Я слышу тебя хорошо. 🤗
            </div>
        </div>

        <div class="fixed inset-x-0 bottom-8 z-20 pl-[270px]">
            <div class="mx-auto flex max-w-[1080px] items-center justify-center gap-6 px-10">
                <div class="h-18 w-18 rounded-full bg-white p-1 shadow-[0_14px_34px_rgba(84,95,167,0.24)]">
                    <div class="h-full w-full rounded-full bg-gradient-to-br from-[#8f96ef] to-[#5d65d5]"></div>
                </div>

                <button
                    v-show="phraseCompleted"
                    type="button"
                    class="inline-flex h-14 min-w-[300px] items-center justify-center rounded-full bg-[#1b045f] px-10 text-sm font-semibold text-white shadow-[0_6px_0_rgba(112,102,189,0.55)] transition hover:bg-[#250875]"
                    @click="$emit('continue')"
                >
                    Продолжить
                </button>
            </div>
        </div>
    </section>
</template>
