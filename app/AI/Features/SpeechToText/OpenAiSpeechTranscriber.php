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
            $sttModel = $this->resolveSttModel();

            $baseParameters = [
                'temperature' => (float) config('ai.features.speech_to_text.temperature', 0),
                'prompt' => $this->speechToTextPrompt(),
            ];
            $normalizedLanguage = $this->normalizeLanguage($language);

            if (is_string($normalizedLanguage)) {
                $baseParameters['language'] = $normalizedLanguage;
            }

            $audioResource = fopen($temporaryAudioPath, 'rb');

            if ($audioResource === false) {
                throw new RuntimeException('Unable to read uploaded audio file.');
            }

            try {
                // TODO: подумать насчет того чтобы сделать асинхронный метод через воркеры
                $response = $this->client->audio()->transcribe([
                    ...$baseParameters,
                    'file' => $audioResource,
                    'model' => $sttModel,
                ]);
            } finally {
                if (is_resource($audioResource)) {
                    fclose($audioResource);
                }
            }

            return $this->sanitizeTranscript(trim((string) $response->text));
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
        $sourceAudioPath = $audioFile->getRealPath();

        if (! is_string($sourceAudioPath) || $sourceAudioPath === '' || ! is_file($sourceAudioPath)) {
            throw new RuntimeException('Unable to access uploaded audio file.');
        }

        $temporaryFilePath = sprintf(
            '%s/%s.%s',
            sys_get_temp_dir(),
            uniqid('stt-audio-', true),
            $extension,
        );

        if (! copy($sourceAudioPath, $temporaryFilePath)) {
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

    private function resolveSttModel(): string
    {
        $model = trim((string) config('ai.features.speech_to_text.model'));

        if ($model === '') {
            throw new RuntimeException('No speech-to-text model configured.');
        }

        return $model;
    }

    private function speechToTextPrompt(): string
    {
        return 'The speaker may switch between Russian and English in one sentence. '
            .'Transcribe exactly what is said and never invent words that are not present in the audio. '
            .'If the audio has no intelligible speech, return an empty string. '
            .'Preserve technical terms and acronyms without translating them (for example: Query Builder, SQL, Eloquent, Laravel, API, MVC, ORM, HTTP, JSON).';
    }
}
