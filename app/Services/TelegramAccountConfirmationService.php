<?php

namespace App\Services;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\InterviewTelegramConfirmation;
use App\Models\Position;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TelegramAccountConfirmationService
{
    /**
     * @param  array{first_name: string, last_name: string, telegram: string, email?: string|null}  $candidateData
     */
    public function startOrReusePendingConfirmation(
        Position $position,
        array $candidateData,
        string $sessionFingerprint,
        string $clientRequestId,
    ): InterviewTelegramConfirmation {
        return DB::transaction(function () use ($position, $candidateData, $sessionFingerprint, $clientRequestId): InterviewTelegramConfirmation {
            $now = now();

            $activeFlowQuery = InterviewTelegramConfirmation::query()
                ->where('position_id', $position->id)
                ->where('session_fingerprint', $sessionFingerprint)
                ->whereNull('superseded_at')
                ->whereNull('interview_id')
                ->where('expires_at', '>', $now)
                ->lockForUpdate();

            $flowForClientRequest = (clone $activeFlowQuery)
                ->where('client_request_id', $clientRequestId)
                ->first();

            if ($flowForClientRequest instanceof InterviewTelegramConfirmation) {
                return $flowForClientRequest;
            }

            $flowForSameCandidateData = (clone $activeFlowQuery)
                ->where('first_name', $candidateData['first_name'])
                ->where('last_name', $candidateData['last_name'])
                ->where('email', $candidateData['email'] ?? null)
                ->where('expected_username', $candidateData['telegram'])
                ->orderByDesc('id')
                ->first();

            if ($flowForSameCandidateData instanceof InterviewTelegramConfirmation) {
                return $flowForSameCandidateData;
            }

            (clone $activeFlowQuery)->update([
                'superseded_at' => $now,
                'failure_reason' => 'superseded_by_new_submission',
            ]);

            $statusToken = $this->generateUniqueStatusToken();

            return InterviewTelegramConfirmation::query()->create([
                'position_id' => $position->id,
                'first_name' => $candidateData['first_name'],
                'last_name' => $candidateData['last_name'],
                'email' => $candidateData['email'] ?? null,
                'expected_username' => $candidateData['telegram'],
                'session_fingerprint' => $sessionFingerprint,
                'client_request_id' => $clientRequestId,
                'status_token' => $statusToken,
                'token_hash' => hash('sha256', $statusToken),
                'expires_at' => $now->copy()->addSeconds($this->resolveConfirmationTtlSeconds()),
                'confirmed_at' => null,
                'used_at' => null,
                'superseded_at' => null,
                'interview_id' => null,
                'telegram_user_id' => null,
                'telegram_chat_id' => null,
                'telegram_username' => null,
                'telegram_update_id' => null,
                'failure_reason' => null,
            ]);
        });
    }

    /**
     * @return array{status: string, interview_id?: int}
     */
    public function resolvePendingConfirmationStatus(
        Position $position,
        string $statusToken,
        string $sessionFingerprint,
    ): array {
        $confirmation = InterviewTelegramConfirmation::query()
            ->where('position_id', $position->id)
            ->where('status_token', $statusToken)
            ->first();

        if (! $confirmation instanceof InterviewTelegramConfirmation) {
            return ['status' => 'not_found'];
        }

        if (! hash_equals($confirmation->session_fingerprint, $sessionFingerprint)) {
            return ['status' => 'not_found'];
        }

        if ($confirmation->interview_id !== null) {
            return [
                'status' => 'confirmed',
                'interview_id' => $confirmation->interview_id,
            ];
        }

        if ($confirmation->superseded_at !== null) {
            return ['status' => 'superseded'];
        }

        if ($confirmation->expires_at !== null && $confirmation->expires_at->isPast()) {
            return ['status' => 'expired'];
        }

        return ['status' => 'pending'];
    }

    /**
     * @param  array{username?: ?string, user_id?: ?int, chat_id?: ?int, chat_type?: ?string, update_id?: ?int}  $telegramIdentity
     * @return array{status: string, interview_id?: int}
     */
    public function confirmByTokenAndUsername(string $token, array $telegramIdentity): array
    {
        return DB::transaction(function () use ($token, $telegramIdentity): array {
            $tokenHash = hash('sha256', $token);
            $now = now();

            $confirmation = InterviewTelegramConfirmation::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $confirmation instanceof InterviewTelegramConfirmation) {
                return ['status' => 'invalid'];
            }

            if ($confirmation->interview_id !== null) {
                return [
                    'status' => 'already_confirmed',
                    'interview_id' => $confirmation->interview_id,
                ];
            }

            if ($confirmation->superseded_at !== null) {
                return ['status' => 'superseded'];
            }

            if ($confirmation->expires_at !== null && $confirmation->expires_at->isPast()) {
                $this->markFailure($confirmation, 'expired');

                return ['status' => 'expired'];
            }

            $chatType = $telegramIdentity['chat_type'] ?? null;

            if ($chatType !== null && $chatType !== 'private') {
                $this->markFailure($confirmation, 'invalid_chat_type');

                return ['status' => 'invalid_chat_type'];
            }

            $normalizedTelegramUsername = $this->normalizeTelegramUsername($telegramIdentity['username'] ?? null);

            if ($normalizedTelegramUsername === null) {
                $this->markFailure($confirmation, 'missing_username');

                return ['status' => 'missing_username'];
            }

            if (! hash_equals($confirmation->expected_username, $normalizedTelegramUsername)) {
                $this->markFailure($confirmation, 'username_mismatch');

                return ['status' => 'username_mismatch'];
            }

            $telegramUserId = $telegramIdentity['user_id'] ?? null;
            $telegramChatId = $telegramIdentity['chat_id'] ?? null;

            if (! is_int($telegramUserId) || ! is_int($telegramChatId)) {
                $this->markFailure($confirmation, 'missing_identity');

                return ['status' => 'missing_identity'];
            }

            $telegramUpdateId = $telegramIdentity['update_id'] ?? null;

            if (is_int($telegramUpdateId)) {
                $existingUpdate = InterviewTelegramConfirmation::query()
                    ->where('telegram_update_id', $telegramUpdateId)
                    ->lockForUpdate()
                    ->first();

                if ($existingUpdate instanceof InterviewTelegramConfirmation && $existingUpdate->id !== $confirmation->id) {
                    return ['status' => 'duplicate_update'];
                }
            }

            $interview = Interview::query()->create([
                'position_id' => $confirmation->position_id,
                'first_name' => $confirmation->first_name,
                'last_name' => $confirmation->last_name,
                'email' => $confirmation->email,
                'telegram' => $confirmation->expected_username,
                'telegram_confirmed_at' => $now,
                'telegram_user_id' => $telegramUserId,
                'telegram_chat_id' => $telegramChatId,
                'telegram_confirmed_username' => $normalizedTelegramUsername,
                'phone' => null,
                'status' => InterviewStatus::Pending,
                'started_at' => $now,
                'completed_at' => null,
            ]);

            $confirmation->forceFill([
                'interview_id' => $interview->id,
                'confirmed_at' => $now,
                'used_at' => $now,
                'telegram_user_id' => $telegramUserId,
                'telegram_chat_id' => $telegramChatId,
                'telegram_username' => $normalizedTelegramUsername,
                'telegram_update_id' => is_int($telegramUpdateId) ? $telegramUpdateId : null,
                'failure_reason' => null,
            ])->save();

            return [
                'status' => 'confirmed',
                'interview_id' => $interview->id,
            ];
        });
    }

    public function resolveSessionFingerprint(string $ipAddress, ?string $userAgent = null): string
    {
        $normalizedIpAddress = trim($ipAddress) === '' ? 'unknown-ip' : trim($ipAddress);

        return hash('sha256', $normalizedIpAddress);
    }

    public function buildTelegramDeepLink(string $statusToken): ?string
    {
        $botUsername = ltrim((string) config('telegram.bot_username', ''), '@');

        if ($botUsername === '') {
            return null;
        }

        return sprintf('https://t.me/%s?start=%s', $botUsername, $statusToken);
    }

    public function resolveTelegramStartReplyText(string $status): string
    {
        return match ($status) {
            'confirmed', 'already_confirmed', 'duplicate_update' => 'Аккаунт подтвержден. Вернитесь в браузер и продолжайте интервью.',
            'missing_token' => 'Чтобы подтвердить аккаунт, вернитесь в форму интервью и откройте ссылку на бота повторно.',
            'missing_username' => 'В Telegram не указан username. Добавьте @username в настройках профиля и повторите подтверждение.',
            'username_mismatch' => 'Username не совпал с указанным в форме. Вернитесь на страницу интервью, исправьте Telegram и отправьте форму заново.',
            'invalid_chat_type' => 'Подтверждение доступно только в личном чате с ботом.',
            'expired' => 'Срок подтверждения истек. Вернитесь на страницу интервью и отправьте форму снова.',
            'superseded' => 'Эта ссылка уже неактуальна. Вернитесь на страницу интервью и отправьте форму снова.',
            default => 'Не удалось подтвердить аккаунт. Вернитесь на страницу интервью и попробуйте снова.',
        };
    }

    private function markFailure(InterviewTelegramConfirmation $confirmation, string $reason): void
    {
        if ($confirmation->failure_reason === $reason) {
            return;
        }

        $confirmation->forceFill([
            'failure_reason' => $reason,
        ])->save();
    }

    private function normalizeTelegramUsername(?string $username): ?string
    {
        if (! is_string($username)) {
            return null;
        }

        $normalized = strtolower(trim($username));

        if (str_starts_with($normalized, '@')) {
            $normalized = substr($normalized, 1);
        }

        if (! preg_match('/^[a-z0-9_]{5,32}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    private function generateUniqueStatusToken(): string
    {
        do {
            $token = Str::random(64);
        } while (InterviewTelegramConfirmation::query()->where('status_token', $token)->exists());

        return $token;
    }

    private function resolveConfirmationTtlSeconds(): int
    {
        return max((int) config('telegram.confirmation_ttl_seconds', 900), 60);
    }
}
