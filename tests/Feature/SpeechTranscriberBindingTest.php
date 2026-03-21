<?php

namespace Tests\Feature;

use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use App\AI\Features\SpeechToText\OpenAiSpeechTranscriber;
use App\AI\Features\SpeechToText\VadSpeechTranscriber;
use Tests\TestCase;

class SpeechTranscriberBindingTest extends TestCase
{
    public function test_it_resolves_openai_transcriber_when_vad_runtime_is_frontend(): void
    {
        config()->set('ai.features.speech_to_text.vad.runtime', 'frontend');

        $transcriber = app(SpeechTranscriber::class);

        $this->assertInstanceOf(OpenAiSpeechTranscriber::class, $transcriber);
    }

    public function test_it_resolves_legacy_vad_transcriber_when_vad_runtime_is_backend(): void
    {
        config()->set('ai.features.speech_to_text.vad.runtime', 'backend');

        $transcriber = app(SpeechTranscriber::class);

        $this->assertInstanceOf(VadSpeechTranscriber::class, $transcriber);
    }

    public function test_it_falls_back_to_openai_transcriber_for_unknown_runtime(): void
    {
        config()->set('ai.features.speech_to_text.vad.runtime', 'unsupported');

        $transcriber = app(SpeechTranscriber::class);

        $this->assertInstanceOf(OpenAiSpeechTranscriber::class, $transcriber);
    }
}
