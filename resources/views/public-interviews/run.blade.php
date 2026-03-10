<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $position?->title }} — интервью</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#eef1ff] text-[#22263f]">
<div
    id="public-interview-run"
    data-questions='@json($questions, JSON_UNESCAPED_UNICODE)'
    data-answer-endpoint-template="{{ route('public-interviews.questions.answer', ['interview' => $interview, 'interviewQuestion' => '__QUESTION_ID__']) }}"
    data-transcribe-endpoint="{{ route('public-interviews.transcribe', ['interview' => $interview]) }}"
    data-answer-time-seconds="{{ $answerTimeSeconds }}"
    data-interview-completed="{{ $interviewCompleted ? '1' : '0' }}"
    data-completion-message="{{ $completionMessage }}"
    class="relative min-h-screen overflow-hidden"
>
    <aside class="fixed inset-y-0 left-0 w-[270px] bg-[#dce2f8] px-12 py-10">
        <div class="text-[34px] font-black tracking-[0.22em] text-[#1f2440]">LARAVEL</div>
    </aside>

    <main class="min-h-screen pl-[270px]">
        <a href="#" class="absolute right-10 top-10 text-sm text-[#61678b] hover:text-[#464c72]">ⓘ Помощь</a>

        <div class="mx-auto w-full max-w-[1080px] px-10 py-12">
            <section id="screen-start" class="grid min-h-[78vh] items-center gap-16 lg:grid-cols-[1.2fr_1fr]">
                <div class="space-y-5">
                    <p class="text-[22px] text-[#353a56]">Привет, {{ $interview->first_name }} {{ $interview->last_name }}</p>
                    <p class="text-sm text-[#5b617d]">Тест приглашает пройти интервью на позицию</p>
                    <h1 class="text-[58px] font-bold leading-tight text-[#202541]">{{ $position?->title }}</h1>

                    <dl class="mt-6 grid max-w-[360px] grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm text-[#5f6481]">
                        <dt>Компания</dt>
                        <dd class="text-[#2f334d]">Тест</dd>
                        <dt>Язык</dt>
                        <dd class="text-[#2f334d]">Русский / English</dd>
                        <dt>Всего вопросов</dt>
                        <dd class="text-[#2f334d]">{{ count($questions) }}</dd>
                    </dl>

                    <button
                        id="start-flow"
                        type="button"
                        class="mt-6 inline-flex h-14 min-w-[260px] items-center justify-center rounded-full bg-[#1b045f] px-10 text-sm font-semibold text-white shadow-[0_6px_0_rgba(112,102,189,0.55)] transition hover:bg-[#250875]"
                    >
                        Начать
                    </button>
                </div>

                <div class="space-y-4">
                    <h2 class="text-3xl font-bold text-[#22263f]">Перед стартом</h2>
                    <p class="text-sm text-[#5a607b]">Перед началом интервью убедитесь, что:</p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-[#d7dcf5] bg-white/70 p-4 text-sm text-[#4e5470]">Вы используете последнюю версию браузера Chrome или Edge</div>
                        <div class="rounded-2xl border border-[#d7dcf5] bg-white/70 p-4 text-sm text-[#4e5470]">Ваши колонки или наушники работают</div>
                        <div class="rounded-2xl border border-[#d7dcf5] bg-white/70 p-4 text-sm text-[#4e5470]">Ваш микрофон включен и работает</div>
                        <div class="rounded-2xl border border-[#d7dcf5] bg-white/70 p-4 text-sm text-[#4e5470]">Вы в тихом помещении и готовы сконцентрироваться</div>
                    </div>
                </div>
            </section>

            <section id="screen-chat" class="hidden min-h-[78vh]">
                <div class="mx-auto max-w-[760px] space-y-5 pb-40">
                    <div class="max-w-[480px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-sm text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                        Привет 👋
                    </div>
                    <div class="max-w-[520px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-sm text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                        Я — Laravel, твой виртуальный интервьюер.
                    </div>
                    <div class="max-w-[620px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-sm text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                        Я запрограммирована оценить твои знания с помощью ряда фундаментальных вопросов.
                        Для ответа на каждый вопрос у тебя будет {{ $position?->answer_time_seconds?->getLabel() ?? '2 минуты' }}.
                    </div>
                    <div class="max-w-[620px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-sm text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                        Чтобы записать ответ, нужно использовать микрофон. Давай проверим, что он включен и работает.
                    </div>

                    <div class="rounded-2xl bg-white px-6 py-5 shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#7a43c9]">Прочитай предложение:</p>
                        <p id="phrase-source" class="mt-2 text-lg text-[#232742]">«Хотите понять других - пристальнее смотрите в самого себя.»</p>
                        <p id="microphone-status" class="mt-3 text-sm text-[#4f556f]">Разрешите доступ к микрофону и проверьте запись.</p>

                        <div class="mt-4 flex flex-wrap gap-3">
                            <button
                                id="microphone-access"
                                type="button"
                                class="inline-flex h-11 items-center justify-center rounded-full border border-[#cfd5f5] bg-white px-6 text-sm font-medium text-[#414766] transition hover:bg-[#f6f7ff]"
                            >
                                Разрешить микрофон
                            </button>
                            <button
                                id="phrase-record-toggle"
                                type="button"
                                class="inline-flex h-11 items-center justify-center rounded-full bg-[#1b045f] px-7 text-sm font-semibold text-white shadow-[0_6px_0_rgba(112,102,189,0.55)] transition hover:bg-[#250875]"
                            >
                                Записать фразу
                            </button>
                        </div>
                    </div>

                    <div id="phrase-user-row" class="hidden justify-end">
                        <div class="max-w-[540px] rounded-2xl rounded-tr-md bg-[#6d76e8] px-5 py-4 text-sm text-white shadow-[0_10px_32px_rgba(93,103,166,0.22)]">
                            <p id="phrase-result"></p>
                        </div>
                    </div>

                    <div id="phrase-success-row" class="hidden max-w-[620px] rounded-2xl rounded-tl-md bg-white px-5 py-4 text-sm text-[#2f344d] shadow-[0_10px_32px_rgba(93,103,166,0.12)]">
                        Все в порядке! Я слышу тебя хорошо. 🤗
                    </div>
                </div>

                <div class="fixed inset-x-0 bottom-8 z-20 pl-[270px]">
                    <div class="mx-auto flex max-w-[1080px] items-center justify-center gap-6 px-10">
                        <div class="h-18 w-18 rounded-full bg-white p-1 shadow-[0_14px_34px_rgba(84,95,167,0.24)]">
                            <div class="h-full w-full rounded-full bg-gradient-to-br from-[#8f96ef] to-[#5d65d5]"></div>
                        </div>

                        <button
                            id="chat-continue"
                            type="button"
                            class="hidden inline-flex h-14 min-w-[300px] items-center justify-center rounded-full bg-[#1b045f] px-10 text-sm font-semibold text-white shadow-[0_6px_0_rgba(112,102,189,0.55)] transition hover:bg-[#250875]"
                        >
                            Продолжить
                        </button>
                    </div>
                </div>
            </section>

            <section id="screen-interview" class="hidden min-h-[78vh] pb-44">
                <div id="question-list" class="mx-auto max-w-[760px] space-y-10"></div>

                <div class="fixed inset-x-0 bottom-8 z-20 pl-[270px]">
                    <div class="mx-auto flex max-w-[1080px] items-center justify-center gap-6 px-10">
                        <button
                            id="skip-answer"
                            type="button"
                            class="inline-flex h-12 min-w-[140px] items-center justify-center rounded-full border border-transparent px-6 text-sm text-[#2e3350] transition hover:bg-white/60"
                        >
                            Не знаю ответ
                        </button>

                        <button
                            id="record-toggle"
                            type="button"
                            class="inline-flex h-14 min-w-[280px] items-center justify-center rounded-full bg-[#1b045f] px-8 text-sm font-semibold text-white shadow-[0_6px_0_rgba(112,102,189,0.55)] transition hover:bg-[#250875]"
                        >
                            Записать ответ
                        </button>
                    </div>

                    <p id="answer-status" class="mt-3 text-center text-sm text-[#545a78]"></p>
                    <p id="speech-fallback" class="mt-1 hidden text-center text-xs text-amber-700">
                        Запись звука не поддерживается в этом браузере. Пожалуйста, используйте Chrome или Safari.
                    </p>
                </div>
            </section>
        </div>
    </main>

    <div id="instructions-modal" class="fixed inset-0 z-40 hidden items-center justify-center bg-[#1f2440]/45 px-4">
        <div class="w-full max-w-[860px] rounded-[30px] bg-white p-8 shadow-[0_32px_80px_rgba(44,50,88,0.35)]">
            <h3 class="text-[32px] font-bold uppercase tracking-wide text-[#7a43c9]">Как отвечать на вопросы</h3>
            <p class="mt-3 text-sm text-[#555b77]">Время на ответ отсчитывается с момента появления вопроса на экране.</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-[#d8dcf5] bg-[#f6f7ff] p-4 text-base font-semibold text-[#252a44]">1. Внимательно прослушайте вопрос</div>
                <div class="rounded-2xl border border-[#d8dcf5] bg-[#f6f7ff] p-4 text-base font-semibold text-[#252a44]">2. Нажмите «Записать ответ»</div>
                <div class="rounded-2xl border border-[#d8dcf5] bg-[#f6f7ff] p-4 text-base font-semibold text-[#252a44]">3. Дайте развернутый ответ, подкрепив его примерами</div>
                <div class="rounded-2xl border border-[#d8dcf5] bg-[#f6f7ff] p-4 text-base font-semibold text-[#252a44]">4. Для сохранения нажмите «Остановить запись»</div>
            </div>

            <button
                id="instructions-start"
                type="button"
                class="mt-8 inline-flex h-14 w-full items-center justify-center rounded-full bg-[#6d76e8] px-8 text-base font-semibold text-white transition hover:bg-[#5c66dd]"
            >
                Поехали!
            </button>
        </div>
    </div>
</div>
</body>
</html>
