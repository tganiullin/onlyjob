<?php

namespace Tests\Feature;

use App\AI\Exceptions\AiProviderException;
use App\AI\Features\SpeechToText\OpenAiSpeechTranscriber;
use Illuminate\Http\UploadedFile;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\Resources\AudioContract;
use OpenAI\Responses\Audio\TranscriptionResponse;
use OpenAI\Responses\Meta\MetaInformation;
use Tests\TestCase;

class OpenAiSpeechTranscriberTest extends TestCase
{
    public function test_it_uses_speech_to_text_feature_config_for_transcription(): void
    {
        config()->set('ai.features.speech_to_text.model', 'stt-primary-model');
        config()->set('ai.features.speech_to_text.temperature', 0.35);

        $capturedParameters = [];

        $audio = \Mockery::mock(AudioContract::class);
        $audio
            ->shouldReceive('transcribe')
            ->once()
            ->withArgs(function (array $parameters) use (&$capturedParameters): bool {
                $capturedParameters = $parameters;

                return $parameters['model'] === 'stt-primary-model'
                    && $parameters['temperature'] === 0.35
                    && is_string($parameters['prompt'])
                    && str_contains($parameters['prompt'], 'never invent words')
                    && $parameters['language'] === 'ru';
            })
            ->andReturn(TranscriptionResponse::from([
                'text' => 'Тестовая транскрипция.',
            ], MetaInformation::from([])));

        $client = \Mockery::mock(ClientContract::class);
        $client
            ->shouldReceive('audio')
            ->once()
            ->andReturn($audio);

        $transcriber = new OpenAiSpeechTranscriber($client);

        $transcript = $transcriber->transcribe(
            UploadedFile::fake()->create('speech.webm', 128, 'audio/webm'),
            'ru-RU',
        );

        $this->assertSame('Тестовая транскрипция.', $transcript);
        $this->assertArrayHasKey('file', $capturedParameters);
    }

    public function test_it_fails_when_speech_to_text_model_is_not_configured(): void
    {
        config()->set('ai.features.speech_to_text.model', '');
        config()->set('ai.features.speech_to_text.temperature', 0);

        $client = \Mockery::mock(ClientContract::class);
        $client
            ->shouldNotReceive('audio');

        $transcriber = new OpenAiSpeechTranscriber($client);

        $this->expectException(AiProviderException::class);

        $transcriber->transcribe(
            UploadedFile::fake()->create('speech.webm', 128, 'audio/webm'),
            'auto',
        );
    }
}
