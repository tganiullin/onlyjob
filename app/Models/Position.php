<?php

namespace App\Models;

use App\Enums\PositionAnswerTime;
use App\Enums\PositionLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    /** @use HasFactory<\Database\Factories\PositionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'minimum_score',
        'answer_time_seconds',
        'level',
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
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
