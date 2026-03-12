<?php

namespace Tests\Feature;

use Telegram\Bot\Laravel\Facades\Telegram;
use Tests\TestCase;

class TelegramWebhookSyncCommandTest extends TestCase
{
    public function test_command_configures_webhook_using_config_values(): void
    {
        config()->set('telegram.default', 'mybot');
        config()->set('telegram.bots.mybot.webhook_url', 'https://example.test/api/telegram/webhook');
        config()->set('telegram.bots.mybot.allowed_updates', ['message']);
        config()->set('telegram.webhook_secret_token', 'secret_token_123');

        $fakeTelegram = new class
        {
            public array $selectedBots = [];

            public array $setWebhookCalls = [];

            public function bot(string $name): self
            {
                $this->selectedBots[] = $name;

                return $this;
            }

            /**
             * @param  array<string, mixed>  $payload
             * @return array{ok: bool}
             */
            public function setWebhook(array $payload): array
            {
                $this->setWebhookCalls[] = $payload;

                return ['ok' => true];
            }
        };

        Telegram::swap($fakeTelegram);

        $this->artisan('telegram:webhook:sync')
            ->expectsOutputToContain('Telegram webhook configured for bot [mybot].')
            ->assertExitCode(0);

        $this->assertSame(['mybot'], $fakeTelegram->selectedBots);
        $this->assertSame([
            'url' => 'https://example.test/api/telegram/webhook',
            'allowed_updates' => ['message'],
            'secret_token' => 'secret_token_123',
        ], $fakeTelegram->setWebhookCalls[0]);
    }

    public function test_command_allows_option_overrides(): void
    {
        config()->set('telegram.default', 'mybot');
        config()->set('telegram.bots.mybot.webhook_url', 'https://example.test/api/telegram/webhook');
        config()->set('telegram.bots.mybot.allowed_updates', null);
        config()->set('telegram.webhook_secret_token', 'secret_token_123');

        $fakeTelegram = new class
        {
            public array $selectedBots = [];

            public array $setWebhookCalls = [];

            public function bot(string $name): self
            {
                $this->selectedBots[] = $name;

                return $this;
            }

            /**
             * @param  array<string, mixed>  $payload
             * @return array{ok: bool}
             */
            public function setWebhook(array $payload): array
            {
                $this->setWebhookCalls[] = $payload;

                return ['ok' => true];
            }
        };

        Telegram::swap($fakeTelegram);

        $this->artisan('telegram:webhook:sync', [
            '--url' => 'https://override.test/custom/webhook',
            '--secret-token' => 'OVERRIDE_TOKEN',
            '--drop-pending-updates' => true,
        ])->assertExitCode(0);

        $this->assertSame(['mybot'], $fakeTelegram->selectedBots);
        $this->assertSame([
            'url' => 'https://override.test/custom/webhook',
            'drop_pending_updates' => true,
            'secret_token' => 'OVERRIDE_TOKEN',
        ], $fakeTelegram->setWebhookCalls[0]);
    }

    public function test_command_fails_for_unknown_bot(): void
    {
        config()->set('telegram.default', 'mybot');
        config()->set('telegram.bots', [
            'mybot' => [
                'token' => 'abc',
            ],
        ]);

        $this->artisan('telegram:webhook:sync', [
            '--bot' => 'unknown-bot',
        ])
            ->expectsOutputToContain('is not configured')
            ->assertExitCode(1);
    }

    public function test_command_fails_for_invalid_secret_token_format(): void
    {
        config()->set('telegram.default', 'mybot');
        config()->set('telegram.bots.mybot.webhook_url', 'https://example.test/api/telegram/webhook');

        $this->artisan('telegram:webhook:sync', [
            '--secret-token' => 'bad token with spaces',
        ])
            ->expectsOutputToContain('Secret token has invalid format')
            ->assertExitCode(1);
    }
}
