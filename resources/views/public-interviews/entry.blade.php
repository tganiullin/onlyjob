<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $position->title }} — интервью</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#eef1ff] text-slate-900">
<div class="grid min-h-screen lg:grid-cols-[minmax(280px,0.9fr)_minmax(640px,1fr)]">
    <aside class="bg-[#dde2f9] px-10 py-10">
        <div class="mx-auto w-full max-w-[360px]">
            <div class="text-[34px] font-black tracking-[0.22em] text-[#1f2440]">LARAVEL</div>

            <div class="mt-32 space-y-4">
                <p class="text-[20px] leading-tight text-[#2b2f45]">Привет 👋</p>
                <p class="text-sm text-[#4b4f67]">Тест приглашает пройти интервью на позицию</p>
                <h1 class="text-[48px] font-bold leading-tight text-[#1f2440]">{{ $position->title }}</h1>
            </div>

            <dl class="mt-8 grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm text-[#5c6076]">
                <dt class="opacity-80">Компания</dt>
                <dd class="text-[#2f334c]">Тест</dd>

                <dt class="opacity-80">Язык</dt>
                <dd class="text-[#2f334c]">Русский / English</dd>

                <dt class="opacity-80">Всего вопросов</dt>
                <dd class="text-[#2f334c]">{{ $position->questions_count }}</dd>
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

                <form method="post" action="{{ route('public-positions.start', ['token' => $position->public_token]) }}" class="space-y-4">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[#636985]">Имя</label>
                            <input
                                id="first_name"
                                name="first_name"
                                type="text"
                                value="{{ old('first_name') }}"
                                class="h-12 w-full rounded-xl border border-[#d8dcf2] bg-white px-3 text-sm text-[#1f2440] outline-none transition focus:border-[#8b92f0]"
                                required
                            >
                            @error('first_name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="last_name" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[#636985]">Фамилия</label>
                            <input
                                id="last_name"
                                name="last_name"
                                type="text"
                                value="{{ old('last_name') }}"
                                class="h-12 w-full rounded-xl border border-[#d8dcf2] bg-white px-3 text-sm text-[#1f2440] outline-none transition focus:border-[#8b92f0]"
                                required
                            >
                            @error('last_name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="email" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[#636985]">Электронная почта</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            class="h-12 w-full rounded-xl border border-[#d8dcf2] bg-white px-3 text-sm text-[#1f2440] outline-none transition focus:border-[#8b92f0]"
                            required
                        >
                        @error('email')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-start gap-2 rounded-xl border border-[#e0e4f5] bg-white px-3 py-2.5 text-sm text-[#555a73]">
                        <input
                            type="checkbox"
                            name="consent"
                            value="1"
                            class="mt-0.5 h-4 w-4 rounded border-[#ccd2ed]"
                            @checked(old('consent'))
                            required
                        >
                        <span>Я даю согласие на обработку персональных данных и принимаю Политику Конфиденциальности</span>
                    </label>
                    @error('consent')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <button
                        type="submit"
                        class="mt-3 inline-flex h-12 w-full items-center justify-center rounded-full bg-[#d8d4fa] px-6 text-sm font-semibold text-[#3b3f66] transition hover:bg-[#cbc4f5]"
                    >
                        Продолжить
                    </button>
                </form>
            </section>
        </div>
    </main>
</div>
</body>
</html>
