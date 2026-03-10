<?php

use App\AI\Providers\OpenAiProvider;

return [
    'default_provider' => env('AI_PROVIDER', 'openai'),

    'providers' => [
        'openai' => OpenAiProvider::class,
    ],

    'openai' => [
        'model' => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
        'temperature' => (float) env('AI_OPENAI_TEMPERATURE', 0.1),
        'stt_model' => env('AI_OPENAI_STT_MODEL', 'gpt-4o-mini-transcribe'),
        'stt_fallback_model' => env('AI_OPENAI_STT_FALLBACK_MODEL', 'whisper-1'),
        'stt_temperature' => (float) env('AI_OPENAI_STT_TEMPERATURE', 0),
        'stt_prompt' => env(
            'AI_OPENAI_STT_PROMPT',
            'The speaker may switch between Russian and English in one sentence. '
            .'Transcribe exactly what is said and never invent words that are not present in the audio. '
            .'If the audio has no intelligible speech, return an empty string. '
            .'Preserve technical terms and acronyms without translating them (for example: Query Builder, SQL, Eloquent, Laravel, API, MVC, ORM, HTTP, JSON).',
        ),
    ],

    'features' => [
        'interview_review' => [
            'provider' => env('AI_INTERVIEW_REVIEW_PROVIDER', env('AI_PROVIDER', 'openai')),
            'model' => env('AI_INTERVIEW_REVIEW_MODEL', env('AI_OPENAI_MODEL', 'gpt-4o-mini')),
            'temperature' => (float) env('AI_INTERVIEW_REVIEW_TEMPERATURE', 0.1),
            'max_tokens' => (int) env('AI_INTERVIEW_REVIEW_MAX_TOKENS', 2500),
            'output_language' => env('AI_INTERVIEW_REVIEW_OUTPUT_LANGUAGE', 'ru'),
        ],
        'position_filling' => [
            'provider' => env('AI_POSITION_FILLING_PROVIDER', env('AI_PROVIDER', 'openai')),
        ],
        'question_generation' => [
            'provider' => env('AI_QUESTION_GENERATION_PROVIDER', env('AI_PROVIDER', 'openai')),
        ],
        'audio_interview' => [
            'provider' => env('AI_AUDIO_INTERVIEW_PROVIDER', env('AI_PROVIDER', 'openai')),
        ],
    ],
];
