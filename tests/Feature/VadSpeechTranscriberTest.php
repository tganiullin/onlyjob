<?php

namespace Tests\Feature;

use App\AI\Features\SpeechToText\Contracts\VoiceActivityDetector;
use App\AI\Features\SpeechToText\Data\VoiceActivityResult;
use App\AI\Features\SpeechToText\OpenAiSpeechTranscriber;
use App\AI\Features\SpeechToText\VadSpeechTranscriber;
use Database\Seeders\AiPromptSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\Resources\AudioContract;
use OpenAI\Responses\Audio\TranscriptionResponse;
use OpenAI\Responses\Meta\MetaInformation;
use Tests\TestCase;

class VadSpeechTranscriberTest extends TestCase
{
    public function test_it_short_circuits_openai_when_vad_detects_no_speech(): void
    {
        config()->set('ai.features.speech_to_text.model', 'gpt-4o-mini-transcribe');
        config()->set('ai.features.speech_to_text.temperature', 0);

        Log::spy();

        $client = \Mockery::mock(ClientContract::class);
        $client->shouldNotReceive('audio');

        $detector = new class implements VoiceActivityDetector
        {
            public function detect(string $audioPath): VoiceActivityResult
            {
                return new VoiceActivityResult(
                    hasSpeech: false,
                    audioDurationSeconds: 4.2,
                    speechDurationSeconds: 0.18,
                    reason: 'below_min_speech_threshold',
                );
            }
        };

        $transcriber = new VadSpeechTranscriber(
            new OpenAiSpeechTranscriber($client),
            $detector,
        );

        $transcript = $transcriber->transcribe(
            UploadedFile::fake()->create('silence.webm', 64, 'audio/webm'),
            'auto',
        );

        $this->assertSame('', $transcript);

        Log::shouldHaveReceived('info')->once()->withArgs(
            function (string $message, array $context): bool {
                return $message === 'Speech-to-text VAD analysis completed.'
                    && $context['has_speech'] === false
                    && $context['audio_duration_seconds'] === 4.2
                    && $context['speech_duration_seconds'] === 0.18
                    && $context['reason'] === 'below_min_speech_threshold';
            }
        );
    }

    public function test_it_delegates_to_openai_when_vad_detects_speech(): void
    {
        $this->seed(AiPromptSeeder::class);

        config()->set('ai.features.speech_to_text.model', 'stt-primary-model');
        config()->set('ai.features.speech_to_text.temperature', 0.15);

        $audio = \Mockery::mock(AudioContract::class);
        $audio
            ->shouldReceive('transcribe')
            ->once()
            ->withArgs(function (array $parameters): bool {
                return $parameters['model'] === 'stt-primary-model'
                    && $parameters['temperature'] === 0.15
                    && $parameters['language'] === 'en';
            })
            ->andReturn(TranscriptionResponse::from([
                'text' => 'Hello from delegated transcription.',
            ], MetaInformation::from([])));

        $client = \Mockery::mock(ClientContract::class);
        $client
            ->shouldReceive('audio')
            ->once()
            ->andReturn($audio);

        $detector = new class implements VoiceActivityDetector
        {
            public function detect(string $audioPath): VoiceActivityResult
            {
                return new VoiceActivityResult(
                    hasSpeech: true,
                    audioDurationSeconds: 3.4,
                    speechDurationSeconds: 2.1,
                    reason: null,
                );
            }
        };

        $transcriber = new VadSpeechTranscriber(
            new OpenAiSpeechTranscriber($client),
            $detector,
        );

        $transcript = $transcriber->transcribe(
            UploadedFile::fake()->create('speech.webm', 64, 'audio/webm'),
            'en-US',
        );

        $this->assertSame('Hello from delegated transcription.', $transcript);
    }
}
