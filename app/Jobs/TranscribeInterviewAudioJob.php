<?php

namespace App\Jobs;

use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TranscribeInterviewAudioJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15];

    public int $timeout = 90;

    private const CACHE_TTL_MINUTES = 10;

    public function __construct(
        public string $transcriptionKey,
        public string $audioStoragePath,
        public int $interviewId,
        public string $language,
        public ?int $interviewQuestionId = null,
    ) {
        $this->onQueue('high');
    }

    public function handle(SpeechTranscriber $speechTranscriber): void
    {
        $tempFile = $this->downloadToTempFile();

        try {
            $audioFile = new UploadedFile($tempFile, basename($this->audioStoragePath), null, null, true);

            $text = $speechTranscriber->transcribe($audioFile, $this->language);

            Cache::put($this->cacheKey(), [
                'status' => 'completed',
                'text' => $text,
            ], now()->addMinutes(self::CACHE_TTL_MINUTES));
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function downloadToTempFile(): string
    {
        $extension = pathinfo($this->audioStoragePath, PATHINFO_EXTENSION) ?: 'webm';
        $tempFile = sprintf('%s/%s.%s', sys_get_temp_dir(), uniqid('transcribe-', true), $extension);

        $contents = Storage::get($this->audioStoragePath);

        if (! is_string($contents) || $contents === '') {
            throw new \RuntimeException("Audio file not found in storage: {$this->audioStoragePath}");
        }

        file_put_contents($tempFile, $contents);

        return $tempFile;
    }

    public function failed(Throwable $exception): void
    {
        Cache::put($this->cacheKey(), [
            'status' => 'failed',
            'error' => 'Не удалось распознать аудио.',
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        $this->cleanupTempFile();
    }

    private function cacheKey(): string
    {
        return "transcription:{$this->transcriptionKey}";
    }

    private function cleanupTempFile(): void
    {
        if (str_starts_with($this->audioStoragePath, 'temp-transcriptions/')) {
            Storage::delete($this->audioStoragePath);
        }
    }
}
