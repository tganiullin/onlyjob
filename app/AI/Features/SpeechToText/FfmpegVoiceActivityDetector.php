<?php

namespace App\AI\Features\SpeechToText;

use App\AI\Features\SpeechToText\Contracts\VoiceActivityDetector;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Throwable;

final class FfmpegVoiceActivityDetector implements VoiceActivityDetector
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
            return $this->buildFallbackResult(reason: 'ffmpeg_timeout');
        } catch (Throwable $exception) {
            return $this->buildFallbackResult(reason: 'ffmpeg_failed');
        }

        if ($result->failed()) {
            return $this->buildFallbackResult(reason: 'ffmpeg_failed');
        }

        $errorOutput = $result->errorOutput();
        $audioDurationSeconds = $this->resolveAudioDurationSeconds($errorOutput);

        if ($audioDurationSeconds <= 0.0) {
            return $this->buildFallbackResult(reason: 'audio_duration_unavailable');
        }

        $silenceDurationSeconds = min(
            $audioDurationSeconds,
            $this->extractSilenceDurationSeconds($errorOutput),
        );
        $speechDurationSeconds = max(0.0, $audioDurationSeconds - $silenceDurationSeconds);
        $hasSpeech = $speechDurationSeconds >= $this->minSpeechSeconds();

        return new VoiceActivityResult(
            hasSpeech: $hasSpeech,
            audioDurationSeconds: $audioDurationSeconds,
            speechDurationSeconds: $speechDurationSeconds,
            reason: $hasSpeech ? null : 'below_min_speech_threshold',
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildCommand(string $audioPath): array
    {
        return [
            'ffmpeg',
            '-hide_banner',
            '-i',
            $audioPath,
            '-af',
            sprintf(
                'silencedetect=n=%sdB:d=%s',
                $this->normalizeFloat($this->noiseThresholdDb()),
                $this->normalizeFloat($this->minSilenceSeconds()),
            ),
            '-f',
            'null',
            '-',
        ];
    }

    private function extractAudioDurationSeconds(string $errorOutput): float
    {
        if (preg_match('/Duration:\s*(\d{2}):(\d{2}):(\d{2}(?:\.\d+)?)/', $errorOutput, $matches) !== 1) {
            return 0.0;
        }

        $hours = (float) $matches[1];
        $minutes = (float) $matches[2];
        $seconds = (float) $matches[3];

        return max(0.0, ($hours * 3600) + ($minutes * 60) + $seconds);
    }

    private function resolveAudioDurationSeconds(string $errorOutput): float
    {
        $durationFromMetadata = $this->extractAudioDurationSeconds($errorOutput);

        if ($durationFromMetadata > 0.0) {
            return $durationFromMetadata;
        }

        $durationFromProgress = $this->extractAudioDurationFromProgress($errorOutput);

        if ($durationFromProgress > 0.0) {
            return $durationFromProgress;
        }

        return $this->extractAudioDurationFromSilenceEnd($errorOutput);
    }

    private function extractAudioDurationFromProgress(string $errorOutput): float
    {
        if (preg_match_all('/time=(\d{2}):(\d{2}):(\d{2}(?:\.\d+)?)/', $errorOutput, $matches) < 1) {
            return 0.0;
        }

        $lastIndex = count($matches[0]) - 1;

        if ($lastIndex < 0) {
            return 0.0;
        }

        $hours = (float) $matches[1][$lastIndex];
        $minutes = (float) $matches[2][$lastIndex];
        $seconds = (float) $matches[3][$lastIndex];

        return max(0.0, ($hours * 3600) + ($minutes * 60) + $seconds);
    }

    private function extractAudioDurationFromSilenceEnd(string $errorOutput): float
    {
        if (preg_match_all('/silence_end:\s*([0-9]+(?:\.[0-9]+)?)/', $errorOutput, $matches) < 1) {
            return 0.0;
        }

        $lastSilenceEnd = end($matches[1]);

        if (! is_string($lastSilenceEnd)) {
            return 0.0;
        }

        return max(0.0, (float) $lastSilenceEnd);
    }

    private function extractSilenceDurationSeconds(string $errorOutput): float
    {
        if (preg_match_all('/silence_duration:\s*([0-9]+(?:\.[0-9]+)?)/', $errorOutput, $matches) < 1) {
            return 0.0;
        }

        $totalSilenceDuration = 0.0;

        foreach ($matches[1] as $silenceDuration) {
            $totalSilenceDuration += max(0.0, (float) $silenceDuration);
        }

        return $totalSilenceDuration;
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

    private function normalizeFloat(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3f', $value), '0'), '.');
    }
}
