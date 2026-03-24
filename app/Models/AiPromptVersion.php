<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPromptVersion extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ai_prompt_id',
        'content',
        'version_number',
        'change_note',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiPromptVersion $version): void {
            $version->created_at ??= now();
        });
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class, 'ai_prompt_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
