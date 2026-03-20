<?php

namespace Tests\Feature;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\InterviewTelegramConfirmation;
use App\Models\Position;
use App\Models\Question;
use App\Services\TelegramAccountConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramAccountConfirmationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_or_reuse_pending_confirmation_is_idempotent_for_same_client_request(): void
    {
        $position = Position::factory()->create();
        $service = app(TelegramAccountConfirmationService::class);

        $sessionFingerprint = $service->resolveSessionFingerprint('127.0.0.1', 'phpunit');

        $firstFlow = $service->startOrReusePendingConfirmation(
            $position,
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => null,
                'telegram' => 'john_doe',
            ],
            $sessionFingerprint,
            '20797522-a7f8-43f4-8160-d99e071f89f4',
        );

        $secondFlow = $service->startOrReusePendingConfirmation(
            $position,
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => null,
                'telegram' => 'john_doe',
            ],
            $sessionFingerprint,
            '20797522-a7f8-43f4-8160-d99e071f89f4',
        );

        $this->assertSame($firstFlow->id, $secondFlow->id);
        $this->assertDatabaseCount('interview_telegram_confirmations', 1);
    }

    public function test_start_or_reuse_pending_confirmation_supersedes_previous_flow_when_data_changes(): void
    {
        $position = Position::factory()->create();
        $service = app(TelegramAccountConfirmationService::class);
        $sessionFingerprint = $service->resolveSessionFingerprint('127.0.0.1', 'phpunit');

        $firstFlow = $service->startOrReusePendingConfirmation(
            $position,
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => null,
                'telegram' => 'john_doe',
            ],
            $sessionFingerprint,
            '7408177a-ad7b-495d-9f7f-29f11b0f03da',
        );

        $secondFlow = $service->startOrReusePendingConfirmation(
            $position,
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => null,
                'telegram' => 'john_doe_v2',
            ],
            $sessionFingerprint,
            '11d34a6b-a91d-43f2-933d-3024db5f58f6',
        );

        $this->assertNotSame($firstFlow->id, $secondFlow->id);
        $this->assertDatabaseCount('interview_telegram_confirmations', 2);
        $this->assertDatabaseHas('interview_telegram_confirmations', [
            'id' => $firstFlow->id,
            'failure_reason' => 'superseded_by_new_submission',
        ]);
        $this->assertNotNull(
            InterviewTelegramConfirmation::query()->findOrFail($firstFlow->id)->superseded_at,
        );
    }

    public function test_confirm_by_token_creates_interview_and_marks_flow_as_confirmed(): void
    {
        $position = Position::factory()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $service = app(TelegramAccountConfirmationService::class);
        $sessionFingerprint = $service->resolveSessionFingerprint('127.0.0.1', 'phpunit');

        $flow = $service->startOrReusePendingConfirmation(
            $position,
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => null,
                'telegram' => 'john_doe',
            ],
            $sessionFingerprint,
            'e286c478-c8a0-45df-8dd7-8bafe794fe4d',
        );

        $result = $service->confirmByTokenAndUsername($flow->status_token, [
            'username' => 'john_doe',
            'user_id' => 123456,
            'chat_id' => 123456,
            'chat_type' => 'private',
            'update_id' => 987654,
        ]);

        $this->assertSame('confirmed', $result['status']);

        $interview = Interview::query()->firstOrFail();
        $this->assertSame($position->id, $interview->position_id);
        $this->assertSame(InterviewStatus::PendingInterview, $interview->status);
        $this->assertSame('john_doe', $interview->telegram_confirmed_username);
        $this->assertNotNull($interview->telegram_confirmed_at);
        $this->assertSame(1, $interview->interviewQuestions()->count());

        $confirmedFlow = InterviewTelegramConfirmation::query()->findOrFail($flow->id);
        $this->assertSame($interview->id, $confirmedFlow->interview_id);
        $this->assertNotNull($confirmedFlow->confirmed_at);
        $this->assertNotNull($confirmedFlow->used_at);
    }
}
