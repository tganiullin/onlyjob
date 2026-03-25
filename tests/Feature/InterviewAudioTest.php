<?php

namespace Tests\Feature;

use App\Jobs\TranscribeInterviewAudioJob;
use App\Models\Interview;
use App\Models\Position;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InterviewAudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_transcribe_with_question_id_saves_audio_file_to_storage(): void
    {
        Storage::fake();
        Queue::fake();

        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => 'pending_interview',
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 111111,
            'telegram_chat_id' => 111111,
            'telegram_confirmed_username' => 'audio_test_user',
        ]);

        $interviewQuestion = $interview->interviewQuestions()->firstOrFail();

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->post(route('public-interviews.transcribe', ['interview' => $interview]), [
                'audio' => UploadedFile::fake()->create('speech.webm', 128, 'audio/webm'),
                'language' => 'auto',
                'interview_question_id' => $interviewQuestion->id,
            ]);

        $response
            ->assertOk()
            ->assertJson(['status' => 'processing']);

        $interviewQuestion->refresh();
        $this->assertNotNull($interviewQuestion->candidate_answer_audio_path);

        Storage::assertExists($interviewQuestion->candidate_answer_audio_path);

        Queue::assertPushed(TranscribeInterviewAudioJob::class, function (TranscribeInterviewAudioJob $job) use ($interviewQuestion): bool {
            return $job->interviewQuestionId === $interviewQuestion->id;
        });
    }

    public function test_transcribe_without_question_id_saves_audio_to_temp_path(): void
    {
        Storage::fake();
        Queue::fake();

        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => 'pending_interview',
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 222222,
            'telegram_chat_id' => 222222,
            'telegram_confirmed_username' => 'mic_test_user',
        ]);

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->post(route('public-interviews.transcribe', ['interview' => $interview]), [
                'audio' => UploadedFile::fake()->create('phrase.webm', 64, 'audio/webm'),
                'language' => 'auto',
            ]);

        $response
            ->assertOk()
            ->assertJson(['status' => 'processing']);

        $tempFiles = collect(Storage::allFiles('temp-transcriptions'));
        $this->assertCount(1, $tempFiles);

        Queue::assertPushed(TranscribeInterviewAudioJob::class, function (TranscribeInterviewAudioJob $job): bool {
            return $job->interviewQuestionId === null
                && str_starts_with($job->audioStoragePath, 'temp-transcriptions/');
        });
    }

    public function test_transcribe_ignores_question_id_belonging_to_another_interview(): void
    {
        Storage::fake();
        Queue::fake();

        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => 'pending_interview',
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 333333,
            'telegram_chat_id' => 333333,
            'telegram_confirmed_username' => 'foreign_q_user',
        ]);

        $foreignInterview = Interview::factory()->create([
            'position_id' => $position->id,
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 444444,
            'telegram_chat_id' => 444444,
            'telegram_confirmed_username' => 'foreign_owner_user',
        ]);

        $foreignQuestion = $foreignInterview->interviewQuestions()->firstOrFail();

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->post(route('public-interviews.transcribe', ['interview' => $interview]), [
                'audio' => UploadedFile::fake()->create('speech.webm', 128, 'audio/webm'),
                'language' => 'auto',
                'interview_question_id' => $foreignQuestion->id,
            ]);

        $response->assertOk()->assertJson(['status' => 'processing']);

        $foreignQuestion->refresh();
        $this->assertNull($foreignQuestion->candidate_answer_audio_path);

        $tempFiles = collect(Storage::allFiles('temp-transcriptions'));
        $this->assertCount(1, $tempFiles);
    }

    public function test_audio_stream_route_returns_audio_for_authenticated_user(): void
    {
        Storage::fake();

        $position = Position::factory()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
        ]);

        $interviewQuestion = $interview->interviewQuestions()->firstOrFail();

        $audioPath = sprintf('interview-audio/%d/%d.webm', $interview->id, $interviewQuestion->id);
        Storage::put($audioPath, 'fake-audio-content');

        $interviewQuestion->forceFill([
            'candidate_answer_audio_path' => $audioPath,
        ])->save();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('interview-audio.stream', ['interviewQuestion' => $interviewQuestion]));

        $response->assertOk();
    }

    public function test_audio_stream_route_returns_404_when_no_audio_path(): void
    {
        $position = Position::factory()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
        ]);

        $interviewQuestion = $interview->interviewQuestions()->firstOrFail();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('interview-audio.stream', ['interviewQuestion' => $interviewQuestion]));

        $response->assertNotFound();
    }

    public function test_audio_stream_route_requires_authentication(): void
    {
        $position = Position::factory()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
        ]);

        $interviewQuestion = $interview->interviewQuestions()->firstOrFail();

        $interviewQuestion->forceFill([
            'candidate_answer_audio_path' => 'interview-audio/1/1.webm',
        ])->save();

        $response = $this->getJson(route('interview-audio.stream', ['interviewQuestion' => $interviewQuestion]));

        $response->assertUnauthorized();
    }
}
