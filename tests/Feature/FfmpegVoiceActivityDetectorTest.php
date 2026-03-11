<?php

namespace Tests\Feature;

use App\AI\Features\SpeechToText\FfmpegVoiceActivityDetector;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class FfmpegVoiceActivityDetectorTest extends TestCase
{
    public function test_it_marks_silence_as_no_speech_with_reason(): void
    {
        config()->set('ai.features.speech_to_text.vad.enabled', true);
        config()->set('ai.features.speech_to_text.vad.min_speech_seconds', 0.5);
        config()->set('ai.features.speech_to_text.vad.fail_open', true);

        Process::fake([
            '*' => Process::result(
                errorOutput: <<<'FFMPEG'
Input #0, matroska,webm, from 'audio.webm':
  Duration: 00:00:04.20, start: 0.000000, bitrate: 53 kb/s
[silencedetect @ 0x1] silence_start: 0
[silencedetect @ 0x1] silence_end: 4.02 | silence_duration: 4.02
FFMPEG,
            ),
        ]);

        $detector = new FfmpegVoiceActivityDetector;
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
            '*' => Process::result(
                errorOutput: <<<'FFMPEG'
Input #0, matroska,webm, from 'audio.webm':
  Duration: 00:00:03.00, start: 0.000000, bitrate: 64 kb/s
[silencedetect @ 0x2] silence_start: 0
[silencedetect @ 0x2] silence_end: 1.00 | silence_duration: 1.00
FFMPEG,
            ),
        ]);

        $detector = new FfmpegVoiceActivityDetector;
        $result = $detector->detect($this->fakeAudioPath());

        $this->assertTrue($result->hasSpeech);
        $this->assertNull($result->reason);
        $this->assertEqualsWithDelta(3.0, $result->audioDurationSeconds, 0.001);
        $this->assertEqualsWithDelta(2.0, $result->speechDurationSeconds, 0.001);
    }

    public function test_it_uses_progress_time_when_duration_metadata_is_missing(): void
    {
        config()->set('ai.features.speech_to_text.vad.enabled', true);
        config()->set('ai.features.speech_to_text.vad.min_speech_seconds', 0.5);
        config()->set('ai.features.speech_to_text.vad.fail_open', true);

        Process::fake([
            '*' => Process::result(
                errorOutput: <<<'FFMPEG'
Input #0, matroska,webm, from 'audio.webm':
  Duration: N/A, start: 0.000000, bitrate: N/A
[silencedetect @ 0x2] silence_start: 0
[silencedetect @ 0x2] silence_end: 1.00 | silence_duration: 1.00
size=N/A time=00:00:03.40 bitrate=N/A speed= 120x
FFMPEG,
            ),
        ]);

        $detector = new FfmpegVoiceActivityDetector;
        $result = $detector->detect($this->fakeAudioPath());

        $this->assertTrue($result->hasSpeech);
        $this->assertNull($result->reason);
        $this->assertEqualsWithDelta(3.4, $result->audioDurationSeconds, 0.001);
        $this->assertEqualsWithDelta(2.4, $result->speechDurationSeconds, 0.001);
    }

    public function test_it_uses_fail_open_when_ffmpeg_fails(): void
    {
        config()->set('ai.features.speech_to_text.vad.enabled', true);
        config()->set('ai.features.speech_to_text.vad.fail_open', true);

        Process::fake([
            '*' => Process::result(errorOutput: 'ffmpeg error', exitCode: 1),
        ]);

        $detector = new FfmpegVoiceActivityDetector;
        $result = $detector->detect($this->fakeAudioPath());

        $this->assertTrue($result->hasSpeech);
        $this->assertSame('ffmpeg_failed', $result->reason);
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

        $detector = new FfmpegVoiceActivityDetector;
        $result = $detector->detect($this->fakeAudioPath());

        $this->assertFalse($result->hasSpeech);
        $this->assertSame('ffmpeg_timeout', $result->reason);
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
        $process = new SymfonyProcess(['ffmpeg', '-version']);
        $originalException = new SymfonyProcessTimedOutException($process, SymfonyProcessTimedOutException::TYPE_GENERAL);

        return new ProcessTimedOutException($originalException, new ProcessResult($process));
    }
}
