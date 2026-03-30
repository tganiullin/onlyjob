<?php

namespace App\Http\Controllers;

use App\Services\TelegramAccountConfirmationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

class TelegramWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $configuredSecretToken = (string) config('telegram.webhook_secret_token', '');

        if ($configuredSecretToken !== '') {
            $receivedSecretToken = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

            if (! hash_equals($configuredSecretToken, $receivedSecretToken)) {
                abort(403);
            }
        }

        $updatePayload = $request->all();
        $messageText = (string) ($updatePayload['message']['text'] ?? '');
        $messageEntities = $updatePayload['message']['entities'] ?? [];
        $hasBotCommandEntity = false;

        if (is_array($messageEntities)) {
            foreach ($messageEntities as $messageEntity) {
                if (is_array($messageEntity) && ($messageEntity['type'] ?? null) === 'bot_command') {
                    $hasBotCommandEntity = true;
                    break;
                }
            }
        }

        $chatId = isset($updatePayload['message']['chat']['id']) ? (int) $updatePayload['message']['chat']['id'] : null;
        $startCommandMatches = [];

        if (
            ! $hasBotCommandEntity
            && preg_match('/^\/start(?:@\w+)?(?:\s+([A-Za-z0-9_-]{1,64}))?$/', $messageText, $startCommandMatches) === 1
        ) {
            $status = 'missing_token';
            $token = (string) ($startCommandMatches[1] ?? '');
            $confirmationService = app(TelegramAccountConfirmationService::class);

            if ($token === '') {
                $replyText = $confirmationService->resolveTelegramStartReplyText($status);
            } else {
                $result = $confirmationService->confirmByTokenAndUsername(
                    $token,
                    [
                        'username' => $updatePayload['message']['from']['username'] ?? null,
                        'user_id' => isset($updatePayload['message']['from']['id']) ? (int) $updatePayload['message']['from']['id'] : null,
                        'chat_id' => $chatId,
                        'chat_type' => $updatePayload['message']['chat']['type'] ?? null,
                        'update_id' => isset($updatePayload['update_id']) ? (int) $updatePayload['update_id'] : null,
                    ],
                );
                $status = (string) ($result['status'] ?? 'invalid');
                $replyText = $confirmationService->resolveTelegramStartReplyText($status);
            }

            if (is_int($chatId)) {
                return response()->json([
                    'method' => 'sendMessage',
                    'chat_id' => $chatId,
                    'text' => $replyText,
                ]);
            }

            return response()->json([
                'ok' => true,
                'status' => $status,
            ]);
        }

        try {
            Telegram::commandsHandler(true);
        } catch (Throwable $e) {
            Log::warning('Telegram commandsHandler failed', [
                'exception' => $e->getMessage(),
                'update_id' => $updatePayload['update_id'] ?? null,
            ]);
        }

        return response()->json([
            'ok' => true,
        ]);
    }
}
