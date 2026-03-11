<?php

namespace App\AI\Features\SpeechToText;

final readonly class VoiceActivityResult
{
    public function __construct(
        public bool $hasSpeech,
        public float $audioDurationSeconds,
        public float $speechDurationSeconds,
        public ?string $reason = null,
    ) {}
}
