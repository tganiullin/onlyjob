<?php

namespace App\AI\Features\SpeechToText;

use App\AI\Features\SpeechToText\Contracts\VoiceActivityDetector;
use App\AI\Features\SpeechToText\Data\VoiceActivityResult;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Throwable;

final class NodeVoiceActivityDetector implements VoiceActivityDetector
{
    public function detect(string $audioPath): VoiceActivityResult
    {
        if (! $this->isEnabled()) {
            return new VoiceActivityResult(
                hasSpeech: true,
                audioDurationSeconds: 0.0,
                speechDurationSeconds: 0.0,
                reason: 'vad_disabled',
            );
        }

        try {
            $result = Process::timeout($this->timeoutSeconds())->run($this->buildCommand($audioPath));
        } catch (ProcessTimedOutException $exception) {
            return $this->buildFallbackResult(reason: 'node_vad_timeout');
        } catch (Throwable $exception) {
            return $this->buildFallbackResult(reason: 'node_vad_failed');
        }

        if ($result->failed()) {
            return $this->buildFallbackResult(reason: 'node_vad_failed');
        }

        $payload = json_decode($result->output(), true);

        if (! is_array($payload)) {
            return $this->buildFallbackResult(reason: 'node_vad_invalid_output');
        }

        $audioDurationSeconds = isset($payload['audioDurationSeconds']) ? (float) $payload['audioDurationSeconds'] : 0.0;
        $speechDurationSeconds = isset($payload['speechDurationSeconds']) ? (float) $payload['speechDurationSeconds'] : 0.0;
        $hasSpeech = (bool) ($payload['hasSpeech'] ?? false);
        $reason = isset($payload['reason']) && is_string($payload['reason']) ? $payload['reason'] : null;

        if ($audioDurationSeconds < 0 || $speechDurationSeconds < 0) {
            return $this->buildFallbackResult(reason: 'node_vad_invalid_output');
        }

        return new VoiceActivityResult(
            hasSpeech: $hasSpeech,
            audioDurationSeconds: $audioDurationSeconds,
            speechDurationSeconds: $speechDurationSeconds,
            reason: $reason,
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildCommand(string $audioPath): array
    {
        return [
            $this->nodeBinary(),
            $this->scriptPath(),
            '--audio-path',
            $audioPath,
            '--noise-threshold-db',
            $this->normalizeFloat($this->noiseThresholdDb()),
            '--min-silence-seconds',
            $this->normalizeFloat($this->minSilenceSeconds()),
            '--min-speech-seconds',
            $this->normalizeFloat($this->minSpeechSeconds()),
            '--timeout-seconds',
            (string) $this->timeoutSeconds(),
        ];
    }

    private function buildFallbackResult(string $reason): VoiceActivityResult
    {
        return new VoiceActivityResult(
            hasSpeech: $this->isFailOpen(),
            audioDurationSeconds: 0.0,
            speechDurationSeconds: 0.0,
            reason: $reason,
        );
    }

    private function isEnabled(): bool
    {
        return (bool) config('ai.features.speech_to_text.vad.enabled', true);
    }

    private function isFailOpen(): bool
    {
        return (bool) config('ai.features.speech_to_text.vad.fail_open', true);
    }

    private function noiseThresholdDb(): float
    {
        return (float) config('ai.features.speech_to_text.vad.noise_threshold_db', -45);
    }

    private function minSilenceSeconds(): float
    {
        return max(0.0, (float) config('ai.features.speech_to_text.vad.min_silence_seconds', 0.2));
    }

    private function minSpeechSeconds(): float
    {
        return max(0.0, (float) config('ai.features.speech_to_text.vad.min_speech_seconds', 0.5));
    }

    private function timeoutSeconds(): int
    {
        return max(1, (int) config('ai.features.speech_to_text.vad.timeout_seconds', 5));
    }

    private function nodeBinary(): string
    {
        return (string) config('ai.features.speech_to_text.vad.node_binary', 'node');
    }

    private function scriptPath(): string
    {
        return base_path((string) config('ai.features.speech_to_text.vad.node_script', 'scripts/vad.mjs'));
    }

    private function normalizeFloat(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3f', $value), '0'), '.');
    }
}
