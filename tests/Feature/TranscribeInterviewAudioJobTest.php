<?php

namespace Tests\Feature;

use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use App\Jobs\TranscribeInterviewAudioJob;
use App\Models\Interview;
use App\Models\Position;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscribeInterviewAudioJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_transcribes_audio_and_writes_completed_status_to_cache(): void
    {
        Storage::fake();

        $position = Position::factory()->create();
        Question::factory()->create(['position_id' => $position->id, 'sort_order' => 1]);
        $interview = Interview::factory()->create(['position_id' => $position->id]);

        $audioPath = 'interview-audio/1/1.webm';
        Storage::put($audioPath, 'fake-audio-content');

        $fakeTranscriber = new class implements SpeechTranscriber
        {
            public function transcribe(UploadedFile $audioFile, string $language): string
            {
                return 'Transcribed answer text.';
            }
        };

        $this->app->instance(SpeechTranscriber::class, $fakeTranscriber);

        $key = 'test-transcription-key';
        Cache::put("transcription:{$key}", ['status' => 'processing'], now()->addMinutes(10));

        $job = new TranscribeInterviewAudioJob($key, $audioPath, $interview->id, 'auto');
        $job->handle($fakeTranscriber);

        $cached = Cache::get("transcription:{$key}");
        $this->assertIsArray($cached);
        $this->assertSame('completed', $cached['status']);
        $this->assertSame('Transcribed answer text.', $cached['text']);
    }

    public function test_job_writes_failed_status_to_cache_on_failure(): void
    {
        Storage::fake();

        $position = Position::factory()->create();
        Question::factory()->create(['position_id' => $position->id, 'sort_order' => 1]);
        $interview = Interview::factory()->create(['position_id' => $position->id]);

        $audioPath = 'temp-transcriptions/fail-key.webm';
        Storage::put($audioPath, 'fake-audio-content');

        $key = 'fail-key';
        Cache::put("transcription:{$key}", ['status' => 'processing'], now()->addMinutes(10));

        $job = new TranscribeInterviewAudioJob($key, $audioPath, $interview->id, 'auto');
        $job->failed(new \RuntimeException('OpenAI API error'));

        $cached = Cache::get("transcription:{$key}");
        $this->assertIsArray($cached);
        $this->assertSame('failed', $cached['status']);
        $this->assertArrayHasKey('error', $cached);
    }

    public function test_job_logs_failure_on_failed(): void
    {
        Storage::fake();
        Log::spy();

        $position = Position::factory()->create();
        Question::factory()->create(['position_id' => $position->id, 'sort_order' => 1]);
        $interview = Interview::factory()->create(['position_id' => $position->id]);

        $audioPath = 'temp-transcriptions/log-fail-key.webm';
        Storage::put($audioPath, 'fake-audio-content');

        $key = 'log-fail-key';
        $job = new TranscribeInterviewAudioJob($key, $audioPath, $interview->id, 'auto', 42);
        $job->failed(new \RuntimeException('OpenAI API error'));

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($key, $interview): bool {
            return $message === 'transcribe.job.failed'
                && $context['transcription_key'] === $key
                && $context['interview_id'] === $interview->id
                && $context['interview_question_id'] === 42
                && $context['exception'] === \RuntimeException::class
                && $context['message'] === 'OpenAI API error';
        })->once();
    }

    public function test_job_cleans_up_temp_files_on_failure(): void
    {
        Storage::fake();

        $position = Position::factory()->create();
        Question::factory()->create(['position_id' => $position->id, 'sort_order' => 1]);
        $interview = Interview::factory()->create(['position_id' => $position->id]);

        $audioPath = 'temp-transcriptions/cleanup-key.webm';
        Storage::put($audioPath, 'fake-audio-content');

        $key = 'cleanup-key';
        $job = new TranscribeInterviewAudioJob($key, $audioPath, $interview->id, 'auto');
        $job->failed(new \RuntimeException('API error'));

        Storage::assertMissing($audioPath);
    }

    public function test_job_does_not_delete_permanent_audio_on_failure(): void
    {
        Storage::fake();

        $position = Position::factory()->create();
        Question::factory()->create(['position_id' => $position->id, 'sort_order' => 1]);
        $interview = Interview::factory()->create(['position_id' => $position->id]);

        $audioPath = 'interview-audio/1/1.webm';
        Storage::put($audioPath, 'fake-audio-content');

        $key = 'permanent-key';
        $job = new TranscribeInterviewAudioJob($key, $audioPath, $interview->id, 'auto');
        $job->failed(new \RuntimeException('API error'));

        Storage::assertExists($audioPath);
    }
}
