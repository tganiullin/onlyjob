<?php

namespace App\AI\Features\SpeechToText;

use App\AI\Exceptions\AiProviderException;
use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use Illuminate\Http\UploadedFile;
use OpenAI\Contracts\ClientContract;
use RuntimeException;
use Throwable;

final class OpenAiSpeechTranscriber implements SpeechTranscriber
{
    public function __construct(
        public ClientContract $client,
    ) {}

    public function transcribe(UploadedFile $audioFile, string $language): string
    {
        $temporaryAudioPath = $this->createTemporaryAudioFile($audioFile);

        try {
            $baseParameters = [
                'temperature' => (float) config('ai.openai.stt_temperature', 0),
            ];
            $normalizedLanguage = $this->normalizeLanguage($language);

            if (is_string($normalizedLanguage)) {
                $baseParameters['language'] = $normalizedLanguage;
            }

            $sttPrompt = trim((string) config('ai.openai.stt_prompt', ''));

            if ($sttPrompt !== '') {
                $baseParameters['prompt'] = $sttPrompt;
            }

            $lastException = null;

            foreach ($this->resolveSttModels() as $sttModel) {
                $audioResource = fopen($temporaryAudioPath, 'rb');

                if ($audioResource === false) {
                    throw new RuntimeException('Unable to read uploaded audio file.');
                }

                try {
                    $response = $this->client->audio()->transcribe([
                        ...$baseParameters,
                        'file' => $audioResource,
                        'model' => $sttModel,
                    ]);

                    $transcript = $this->sanitizeTranscript(trim((string) $response->text));

                    if ($transcript !== '') {
                        return $transcript;
                    }
                } catch (Throwable $exception) {
                    $lastException = $exception;
                } finally {
                    if (is_resource($audioResource)) {
                        fclose($audioResource);
                    }
                }
            }

            if ($lastException !== null) {
                throw $lastException;
            }

            return '';
        } catch (Throwable $exception) {
            throw AiProviderException::requestFailed('openai-stt', $exception);
        } finally {
            if (is_file($temporaryAudioPath)) {
                unlink($temporaryAudioPath);
            }
        }
    }

    private function sanitizeTranscript(string $transcript): string
    {
        if ($transcript === '') {
            return '';
        }

        $normalizedTranscript = strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $transcript));
        $normalizedTranscript = trim((string) preg_replace('/\s+/u', ' ', $normalizedTranscript));
        $promptLeakFingerprint = 'query builder sql eloquent laravel api mvc orm http json';

        if ($normalizedTranscript === $promptLeakFingerprint) {
            return '';
        }

        return $transcript;
    }

    private function normalizeLanguage(string $language): ?string
    {
        return match ($language) {
            'en-US', 'en-GB', 'en' => 'en',
            'ru-RU', 'ru' => 'ru',
            'auto', 'browser-default', '' => null,
            default => null,
        };
    }

    private function createTemporaryAudioFile(UploadedFile $audioFile): string
    {
        $extension = $this->resolveAudioExtension($audioFile);
        $temporaryFilePath = sprintf(
            '%s/%s.%s',
            sys_get_temp_dir(),
            uniqid('stt-audio-', true),
            $extension,
        );

        if (! copy($audioFile->getRealPath(), $temporaryFilePath)) {
            throw new RuntimeException('Unable to create temporary audio file for transcription.');
        }

        return $temporaryFilePath;
    }

    private function resolveAudioExtension(UploadedFile $audioFile): string
    {
        $supportedExtensions = ['flac', 'm4a', 'mp3', 'mp4', 'mpeg', 'mpga', 'oga', 'ogg', 'wav', 'webm'];
        $clientExtension = strtolower((string) $audioFile->getClientOriginalExtension());

        if (in_array($clientExtension, $supportedExtensions, true)) {
            return $clientExtension;
        }

        return match ((string) $audioFile->getMimeType()) {
            'audio/webm' => 'webm',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/ogg', 'audio/opus' => 'ogg',
            'audio/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'audio/flac', 'audio/x-flac' => 'flac',
            default => 'webm',
        };
    }

    /**
     * @return list<string>
     */
    private function resolveSttModels(): array
    {
        $primaryModel = trim((string) config('ai.openai.stt_model'));
        $fallbackModel = trim((string) config('ai.openai.stt_fallback_model'));
        $models = array_values(array_filter(array_unique([$primaryModel, $fallbackModel])));

        if ($models === []) {
            throw new RuntimeException('No STT model configured.');
        }

        return $models;
    }
}
