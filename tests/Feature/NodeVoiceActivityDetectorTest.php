<?php

namespace Tests\Feature;

use App\AI\Features\SpeechToText\NodeVoiceActivityDetector;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class NodeVoiceActivityDetectorTest extends TestCase
{
    public function test_it_marks_silence_as_no_speech_with_reason(): void
    {
        config()->set('ai.features.speech_to_text.vad.enabled', true);
        config()->set('ai.features.speech_to_text.vad.min_speech_seconds', 0.5);
        config()->set('ai.features.speech_to_text.vad.fail_open', true);

        Process::fake([
            '*' => Process::result(output: json_encode([
                'hasSpeech' => false,
                'audioDurationSeconds' => 4.2,
                'speechDurationSeconds' => 0.18,
                'reason' => 'below_min_speech_threshold',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $detector = new NodeVoiceActivityDetector;
        $result = $detector->detect($this->fakeAudioPath());

        $this->assertFalse($result->hasSpeech);
        $this->assertSame('below_min_speech_threshold', $result->reason);
        $this->assertEqualsWithDelta(4.2, $result->audioDurationSeconds, 0.001);
        $this->assertEqualsWithDelta(0.18, $result->speechDurationSeconds, 0.001);
    }

    public function test_it_marks_detected_speech_as_true(): void
    {
        config()->set('ai.features.speech_to_text.vad.enabled', true);
        config()->set('ai.features.speech_to_text.vad.min_speech_seconds', 0.5);
        config()->set('ai.features.speech_to_text.vad.fail_open', true);

        Process::fake([
            '*' => Process::result(output: json_encode([
                'hasSpeech' => true,
                'audioDurationSeconds' => 3.0,
                'speechDurationSeconds' => 2.0,
                'reason' => null,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $detector = new NodeVoiceActivityDetector;
        $result = $detector->detect($this->fakeAudioPath());

        $this->assertTrue($result->hasSpeech);
        $this->assertNull($result->reason);
        $this->assertEqualsWithDelta(3.0, $result->audioDurationSeconds, 0.001);
        $this->assertEqualsWithDelta(2.0, $result->speechDurationSeconds, 0.001);
    }

    public function test_it_uses_fail_open_when_node_vad_process_fails(): void
    {
        config()->set('ai.features.speech_to_text.vad.enabled', true);
        config()->set('ai.features.speech_to_text.vad.fail_open', true);

        Process::fake([
            '*' => Process::result(errorOutput: 'node vad error', exitCode: 1),
        ]);

        $detector = new NodeVoiceActivityDetector;
        $result = $detector->detect($this->fakeAudioPath());

        $this->assertTrue($result->hasSpeech);
        $this->assertSame('node_vad_failed', $result->reason);
        $this->assertSame(0.0, $result->audioDurationSeconds);
        $this->assertSame(0.0, $result->speechDurationSeconds);
    }

    public function test_it_uses_fail_open_when_node_vad_output_is_invalid_json(): void
    {
        config()->set('ai.features.speech_to_text.vad.enabled', true);
        config()->set('ai.features.speech_to_text.vad.fail_open', true);

        Process::fake([
            '*' => Process::result(output: 'not-json'),
        ]);

        $detector = new NodeVoiceActivityDetector;
        $result = $detector->detect($this->fakeAudioPath());

        $this->assertTrue($result->hasSpeech);
        $this->assertSame('node_vad_invalid_output', $result->reason);
        $this->assertSame(0.0, $result->audioDurationSeconds);
        $this->assertSame(0.0, $result->speechDurationSeconds);
    }

    public function test_it_returns_no_speech_on_timeout_when_fail_open_is_disabled(): void
    {
        config()->set('ai.features.speech_to_text.vad.enabled', true);
        config()->set('ai.features.speech_to_text.vad.fail_open', false);

        Process::fake([
            '*' => fn () => $this->makeTimeoutException(),
        ]);

        $detector = new NodeVoiceActivityDetector;
        $result = $detector->detect($this->fakeAudioPath());

        $this->assertFalse($result->hasSpeech);
        $this->assertSame('node_vad_timeout', $result->reason);
        $this->assertSame(0.0, $result->audioDurationSeconds);
        $this->assertSame(0.0, $result->speechDurationSeconds);
    }

    private function fakeAudioPath(): string
    {
        $file = UploadedFile::fake()->create('speech.webm', 64, 'audio/webm');
        $path = $file->getRealPath();

        return is_string($path) ? $path : '';
    }

    private function makeTimeoutException(): ProcessTimedOutException
    {
        $process = new SymfonyProcess(['node', '-v']);
        $originalException = new SymfonyProcessTimedOutException($process, SymfonyProcessTimedOutException::TYPE_GENERAL);

        return new ProcessTimedOutException($originalException, new ProcessResult($process));
    }
}
