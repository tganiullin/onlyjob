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
        'speech_to_text' => [
            'provider' => env('AI_SPEECH_TO_TEXT_PROVIDER', env('AI_PROVIDER', 'openai')),
            'model' => env('AI_SPEECH_TO_TEXT_MODEL', env('AI_OPENAI_STT_MODEL', 'gpt-4o-mini-transcribe')),
            'temperature' => (float) env('AI_SPEECH_TO_TEXT_TEMPERATURE', env('AI_OPENAI_STT_TEMPERATURE', 0)),
        ],
    ],
];
