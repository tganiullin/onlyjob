@php
    $audioPath = $get('candidate_answer_audio_path');
    $questionId = $get('id');
    $audioUrl = null;
    $mimeType = 'audio/webm';

    if (filled($audioPath) && filled($questionId)) {
        $audioUrl = route('interview-audio.stream', ['interviewQuestion' => $questionId]);

        $ext = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
        $mimeType = match ($ext) {
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'mp3' => 'audio/mpeg',
            default => 'audio/webm',
        };
    }
@endphp

@if ($audioUrl)
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
        <p class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">
            Аудиозапись ответа
        </p>
        <audio controls preload="metadata" class="w-full">
            <source src="{{ $audioUrl }}" type="{{ $mimeType }}">
            Ваш браузер не поддерживает воспроизведение этого аудио.
        </audio>
        <a
            href="{{ $audioUrl }}"
            download
            class="mt-2 inline-flex items-center gap-1 text-xs text-gray-400 underline hover:text-gray-600 dark:hover:text-gray-300"
        >
            Скачать аудио
        </a>
    </div>
@endif
