<?php

namespace App\AI\Features\SpeechToText;

use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use App\AI\Features\SpeechToText\Contracts\VoiceActivityDetector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

final class VadSpeechTranscriber implements SpeechTranscriber
{
    public function __construct(
        private OpenAiSpeechTranscriber $openAiSpeechTranscriber,
        private VoiceActivityDetector $voiceActivityDetector,
    ) {}

    public function transcribe(UploadedFile $audioFile, string $language): string
    {
        $audioPath = $audioFile->getRealPath();

        if (! is_string($audioPath) || $audioPath === '') {
            return $this->openAiSpeechTranscriber->transcribe($audioFile, $language);
        }

        $voiceActivity = $this->voiceActivityDetector->detect($audioPath);

        Log::info('Speech-to-text VAD analysis completed.', [
            'has_speech' => $voiceActivity->hasSpeech,
            'audio_duration_seconds' => $voiceActivity->audioDurationSeconds,
            'speech_duration_seconds' => $voiceActivity->speechDurationSeconds,
            'reason' => $voiceActivity->reason,
        ]);

        if (! $voiceActivity->hasSpeech) {
            return '';
        }

        return $this->openAiSpeechTranscriber->transcribe($audioFile, $language);
    }
}
