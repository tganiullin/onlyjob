<?php

namespace App\Models;

use App\Enums\PositionAnswerTime;
use App\Enums\PositionLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Position extends Model
{
    /** @use HasFactory<\Database\Factories\PositionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'minimum_score',
        'answer_time_seconds',
        'level',
        'is_public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_score' => 'integer',
            'answer_time_seconds' => PositionAnswerTime::class,
            'level' => PositionLevel::class,
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Position $position): void {
            if (! $position->is_public || filled($position->public_token)) {
                return;
            }

            $position->public_token = static::generateUniquePublicToken();
        });
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function companyQuestions(): HasMany
    {
        return $this->hasMany(PositionCompanyQuestion::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getPublicUrlAttribute(): ?string
    {
        if (! $this->is_public || blank($this->public_token)) {
            return null;
        }

        return route('public-positions.show', ['token' => $this->public_token]);
    }

    private static function generateUniquePublicToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::query()->where('public_token', $token)->exists());

        return $token;
    }
}
