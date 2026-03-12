<?php

namespace App\Telegram\Commands;

use App\Services\TelegramAccountConfirmationService;
use Telegram\Bot\Commands\Command;
use Throwable;

class StartCommand extends Command
{
    protected string $name = 'start';

    protected string $description = 'Подтверждение Telegram аккаунта для интервью.';

    protected string $pattern = '{token}';

    public function __construct(private readonly TelegramAccountConfirmationService $confirmationService) {}

    public function handle(): void
    {
        $token = (string) $this->argument('token', '');
        $updatePayload = $this->getUpdate()->toArray();
        $messagePayload = $updatePayload['message'] ?? [];

        if ($token === '') {
            $this->replySafely($this->confirmationService->resolveTelegramStartReplyText('missing_token'));

            return;
        }

        $result = $this->confirmationService->confirmByTokenAndUsername(
            $token,
            [
                'username' => $messagePayload['from']['username'] ?? null,
                'user_id' => isset($messagePayload['from']['id']) ? (int) $messagePayload['from']['id'] : null,
                'chat_id' => isset($messagePayload['chat']['id']) ? (int) $messagePayload['chat']['id'] : null,
                'chat_type' => $messagePayload['chat']['type'] ?? null,
                'update_id' => isset($updatePayload['update_id']) ? (int) $updatePayload['update_id'] : null,
            ],
        );

        $this->replySafely($this->confirmationService->resolveTelegramStartReplyText((string) ($result['status'] ?? 'invalid')));
    }

    private function replySafely(string $text): void
    {
        try {
            $this->replyWithMessage([
                'text' => $text,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
