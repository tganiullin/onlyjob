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
    ],

    'features' => [
        'interview_review' => [
            'provider' => env('AI_INTERVIEW_REVIEW_PROVIDER', env('AI_PROVIDER', 'openai')),
            'model' => env('AI_INTERVIEW_REVIEW_MODEL', env('AI_OPENAI_MODEL', 'gpt-4o-mini')),
            'temperature' => (float) env('AI_INTERVIEW_REVIEW_TEMPERATURE', 0.1),
            'max_tokens' => (int) env('AI_INTERVIEW_REVIEW_MAX_TOKENS', 2500),
            'output_language' => env('AI_INTERVIEW_REVIEW_OUTPUT_LANGUAGE', 'ru'),
        ],
        'question_generation' => [
            'provider' => env('AI_QUESTION_GENERATION_PROVIDER', env('AI_PROVIDER', 'openai')),
            'model' => env('AI_QUESTION_GENERATION_MODEL', env('AI_OPENAI_MODEL', 'gpt-4o-mini')),
            'temperature' => (float) env('AI_QUESTION_GENERATION_TEMPERATURE', 0.2),
            'max_tokens' => (int) env('AI_QUESTION_GENERATION_MAX_TOKENS', 2000),
            'output_language' => env('AI_QUESTION_GENERATION_OUTPUT_LANGUAGE', 'ru'),
        ],
        'company_questions_generation' => [
            'provider' => env('AI_COMPANY_QUESTIONS_GENERATION_PROVIDER', env('AI_PROVIDER', 'openai')),
            'model' => env('AI_COMPANY_QUESTIONS_GENERATION_MODEL', env('AI_OPENAI_MODEL', 'gpt-4o-mini')),
            'temperature' => (float) env('AI_COMPANY_QUESTIONS_GENERATION_TEMPERATURE', 0.3),
            'max_tokens' => (int) env('AI_COMPANY_QUESTIONS_GENERATION_MAX_TOKENS', 2200),
            'output_language' => env('AI_COMPANY_QUESTIONS_GENERATION_OUTPUT_LANGUAGE', 'ru'),
        ],
        'follow_up_generation' => [
            'provider' => env('AI_FOLLOW_UP_GENERATION_PROVIDER', env('AI_PROVIDER', 'openai')),
            'model' => env('AI_FOLLOW_UP_GENERATION_MODEL', env('AI_OPENAI_MODEL', 'gpt-4o-mini')),
            'temperature' => (float) env('AI_FOLLOW_UP_GENERATION_TEMPERATURE', 0.2),
            'max_tokens' => (int) env('AI_FOLLOW_UP_GENERATION_MAX_TOKENS', 500),
            'output_language' => env('AI_FOLLOW_UP_GENERATION_OUTPUT_LANGUAGE', 'ru'),
        ],
        'speech_to_text' => [
            'provider' => env('AI_SPEECH_TO_TEXT_PROVIDER', env('AI_PROVIDER', 'openai')),
            'model' => env('AI_SPEECH_TO_TEXT_MODEL', 'gpt-4o-mini-transcribe'),
            'temperature' => (float) env('AI_SPEECH_TO_TEXT_TEMPERATURE', 0),
            'vad' => [
                'runtime' => env('AI_SPEECH_TO_TEXT_VAD_RUNTIME', 'frontend'),
                'enabled' => (bool) env('AI_SPEECH_TO_TEXT_VAD_ENABLED', true),
                'noise_threshold_db' => (float) env('AI_SPEECH_TO_TEXT_VAD_NOISE_THRESHOLD_DB', -45),
                'min_silence_seconds' => (float) env('AI_SPEECH_TO_TEXT_VAD_MIN_SILENCE_SECONDS', 0.2),
                'min_speech_seconds' => (float) env('AI_SPEECH_TO_TEXT_VAD_MIN_SPEECH_SECONDS', 0.5),
                'timeout_seconds' => (int) env('AI_SPEECH_TO_TEXT_VAD_TIMEOUT_SECONDS', 5),
                'fail_open' => (bool) env('AI_SPEECH_TO_TEXT_VAD_FAIL_OPEN', true),
            ],
        ],
    ],
];
