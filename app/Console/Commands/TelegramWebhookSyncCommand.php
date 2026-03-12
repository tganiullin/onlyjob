<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

class TelegramWebhookSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook:sync
                            {--bot= : Имя бота из config/telegram.php}
                            {--url= : Полный URL webhook}
                            {--secret-token= : Secret token для заголовка X-Telegram-Bot-Api-Secret-Token}
                            {--drop-pending-updates : Удалить pending updates на стороне Telegram}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Устанавливает Telegram webhook из конфигурации проекта.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $botName = (string) ($this->option('bot') ?: config('telegram.default'));
        $bots = (array) config('telegram.bots', []);

        if (! array_key_exists($botName, $bots)) {
            $this->error(sprintf('Bot [%s] is not configured in config/telegram.php.', $botName));

            return self::FAILURE;
        }

        $configuredWebhookUrl = (string) data_get($bots, "{$botName}.webhook_url", '');
        $webhookUrl = trim((string) ($this->option('url') ?: $configuredWebhookUrl));

        if ($webhookUrl === '') {
            $webhookUrl = route('telegram.webhook');
        }

        if ($webhookUrl === '') {
            $this->error('Webhook URL is empty. Set TELEGRAM_WEBHOOK_URL or pass --url option.');

            return self::FAILURE;
        }

        $secretToken = trim((string) ($this->option('secret-token') ?: config('telegram.webhook_secret_token', '')));

        if ($secretToken !== '' && preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secretToken) !== 1) {
            $this->error('Secret token has invalid format. Allowed: A-Z, a-z, 0-9, _ and -. Length: 1-256.');

            return self::FAILURE;
        }

        $payload = [
            'url' => $webhookUrl,
        ];

        if ((bool) $this->option('drop-pending-updates')) {
            $payload['drop_pending_updates'] = true;
        }

        $allowedUpdates = data_get($bots, "{$botName}.allowed_updates");
        if (is_array($allowedUpdates) && $allowedUpdates !== []) {
            $payload['allowed_updates'] = $allowedUpdates;
        }

        if ($secretToken !== '') {
            $payload['secret_token'] = $secretToken;
        }

        try {
            Telegram::bot($botName)->setWebhook($payload);
        } catch (Throwable $throwable) {
            $this->error(sprintf('Failed to set webhook: %s', $throwable->getMessage()));

            return self::FAILURE;
        }

        $this->info(sprintf('Telegram webhook configured for bot [%s].', $botName));
        $this->line(sprintf('URL: %s', $webhookUrl));

        if ($secretToken !== '') {
            $this->line('Secret token has been applied.');
        }

        return self::SUCCESS;
    }
}
