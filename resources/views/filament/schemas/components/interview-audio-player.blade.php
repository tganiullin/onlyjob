@php
    $audioPath = $get('candidate_answer_audio_path');
    $questionId = $get('id');
@endphp

@if (filled($audioPath) && filled($questionId))
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
        <p class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">
            Аудиозапись ответа
        </p>
        <audio controls preload="metadata" class="w-full">
            <source src="{{ route('interview-audio.stream', ['interviewQuestion' => $questionId]) }}" type="audio/webm">
        </audio>
    </div>
@endif
