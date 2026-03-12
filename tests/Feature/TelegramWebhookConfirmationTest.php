<?php

namespace Tests\Feature;

use App\Models\InterviewTelegramConfirmation;
use App\Models\Position;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramWebhookConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telegram.bots.mybot.token', 'test-token');
    }

    public function test_webhook_confirms_pending_flow_and_creates_interview(): void
    {
        config()->set('telegram.webhook_secret_token', 'telegram-secret');
        config()->set('telegram.bot_username', 'onlyjob_test_bot');

        $position = Position::factory()->public()->create([
            'public_token' => 'public-position-token',
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $statusToken = $this->createPendingFlow($position, 'john_doe');

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/telegram/webhook', $this->buildStartUpdatePayload($statusToken, 'john_doe', 12345, 12345, 999001));

        $response
            ->assertOk()
            ->assertJsonPath('method', 'sendMessage')
            ->assertJsonPath('chat_id', 12345);
        $this->assertStringContainsString('Аккаунт подтвержден', (string) $response->json('text'));

        $this->assertDatabaseCount('interviews', 1);
        $this->assertDatabaseHas('interviews', [
            'position_id' => $position->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'telegram' => 'john_doe',
            'telegram_confirmed_username' => 'john_doe',
            'telegram_user_id' => 12345,
            'telegram_chat_id' => 12345,
        ]);

        $this->assertDatabaseHas('interview_telegram_confirmations', [
            'status_token' => $statusToken,
            'expected_username' => 'john_doe',
            'telegram_username' => 'john_doe',
            'telegram_user_id' => 12345,
            'telegram_chat_id' => 12345,
            'telegram_update_id' => 999001,
            'failure_reason' => null,
        ]);
    }

    public function test_webhook_rejects_request_with_invalid_secret_token(): void
    {
        config()->set('telegram.webhook_secret_token', 'telegram-secret');

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-secret')
            ->postJson('/api/telegram/webhook', []);

        $response->assertForbidden();
    }

    public function test_webhook_does_not_confirm_when_username_mismatches(): void
    {
        config()->set('telegram.webhook_secret_token', 'telegram-secret');

        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $statusToken = $this->createPendingFlow($position, 'john_doe');

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/telegram/webhook', $this->buildStartUpdatePayload($statusToken, 'other_user', 45678, 45678, 999002));

        $response
            ->assertOk()
            ->assertJsonPath('method', 'sendMessage')
            ->assertJsonPath('chat_id', 45678);
        $this->assertStringContainsString('Username не совпал', (string) $response->json('text'));

        $this->assertDatabaseCount('interviews', 0);
        $this->assertDatabaseHas('interview_telegram_confirmations', [
            'status_token' => $statusToken,
            'interview_id' => null,
            'failure_reason' => 'username_mismatch',
        ]);
    }

    public function test_webhook_does_not_confirm_expired_token(): void
    {
        config()->set('telegram.webhook_secret_token', 'telegram-secret');

        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $statusToken = $this->createPendingFlow($position, 'john_doe');

        InterviewTelegramConfirmation::query()
            ->where('status_token', $statusToken)
            ->update(['expires_at' => now()->subMinute()]);

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/telegram/webhook', $this->buildStartUpdatePayload($statusToken, 'john_doe', 45678, 45678, 999003));

        $response
            ->assertOk()
            ->assertJsonPath('method', 'sendMessage')
            ->assertJsonPath('chat_id', 45678);
        $this->assertStringContainsString('Срок подтверждения истек', (string) $response->json('text'));

        $this->assertDatabaseCount('interviews', 0);
        $this->assertDatabaseHas('interview_telegram_confirmations', [
            'status_token' => $statusToken,
            'interview_id' => null,
            'failure_reason' => 'expired',
        ]);
    }

    public function test_webhook_duplicate_update_does_not_create_second_interview(): void
    {
        config()->set('telegram.webhook_secret_token', 'telegram-secret');

        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $statusToken = $this->createPendingFlow($position, 'john_doe');

        $payload = $this->buildStartUpdatePayload($statusToken, 'john_doe', 45678, 45678, 999004);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/telegram/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('method', 'sendMessage')
            ->assertJsonPath('chat_id', 45678);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/telegram/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('method', 'sendMessage')
            ->assertJsonPath('chat_id', 45678);

        $this->assertDatabaseCount('interviews', 1);
    }

    private function createPendingFlow(Position $position, string $username): string
    {
        $response = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'telegram' => '@'.$username,
            'client_request_id' => (string) Str::uuid(),
            'consent' => '1',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'pending_confirmation',
            ]);

        return (string) $response->json('status_token');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStartUpdatePayload(
        string $statusToken,
        string $username,
        int $userId,
        int $chatId,
        int $updateId,
    ): array {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => 1,
                'date' => now()->timestamp,
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => $userId,
                    'is_bot' => false,
                    'first_name' => 'John',
                    'username' => $username,
                ],
                'text' => '/start '.$statusToken,
            ],
        ];
    }
}
