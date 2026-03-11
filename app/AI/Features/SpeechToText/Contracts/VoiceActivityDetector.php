<?php

namespace App\AI\Features\SpeechToText\Contracts;

use App\AI\Features\SpeechToText\VoiceActivityResult;

interface VoiceActivityDetector
{
    public function detect(string $audioPath): VoiceActivityResult;
}
