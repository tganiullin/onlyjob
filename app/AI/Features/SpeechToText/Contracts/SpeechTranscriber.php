<?php

namespace App\AI\Features\SpeechToText\Contracts;

use Illuminate\Http\UploadedFile;

interface SpeechTranscriber
{
    public function transcribe(UploadedFile $audioFile, string $language): string;
}
