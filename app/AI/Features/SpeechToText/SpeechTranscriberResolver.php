<?php

namespace App\AI\Features\SpeechToText;

use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use Illuminate\Contracts\Foundation\Application;

class SpeechTranscriberResolver
{
    public function __construct(
        private Application $app,
    ) {}

    public function resolve(): SpeechTranscriber
    {
        $runtime = strtolower((string) config('ai.features.speech_to_text.vad.runtime', 'frontend'));

        return match ($runtime) {
            'backend' => $this->app->make(VadSpeechTranscriber::class),
            default => $this->app->make(OpenAiSpeechTranscriber::class),
        };
    }
}
