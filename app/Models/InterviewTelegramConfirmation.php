<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewTelegramConfirmation extends Model
{
    /** @use HasFactory<\Database\Factories\InterviewTelegramConfirmationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'position_id',
        'interview_id',
        'first_name',
        'last_name',
        'email',
        'expected_username',
        'session_fingerprint',
        'client_request_id',
        'status_token',
        'token_hash',
        'expires_at',
        'confirmed_at',
        'used_at',
        'superseded_at',
        'telegram_user_id',
        'telegram_chat_id',
        'telegram_username',
        'telegram_update_id',
        'failure_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position_id' => 'integer',
            'interview_id' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'used_at' => 'datetime',
            'superseded_at' => 'datetime',
            'telegram_user_id' => 'integer',
            'telegram_chat_id' => 'integer',
            'telegram_update_id' => 'integer',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class)->withTrashed();
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }
}
