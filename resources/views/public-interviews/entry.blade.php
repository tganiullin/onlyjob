<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $position->title }} — интервью</title>
    @vite(['resources/css/app.css', 'resources/js/entry-position-vue.js'])
</head>
<body class="min-h-screen bg-[#eef1ff] text-slate-900">
<div
    id="public-position-entry"
    data-submit-url="{{ route('public-positions.start', ['token' => $position->public_token]) }}"
    data-position-title="{{ $position->title }}"
    data-questions-count="{{ $position->questions_count }}"
    data-answer-time-seconds="{{ $position->answer_time_seconds?->value ?? 120 }}"
    data-policy-url="{{ url('/policy') }}"
    data-logo-url="{{ asset('images/logo.svg') }}"
></div>
</body>
</html>
