<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    /** @use HasFactory<\Database\Factories\QuestionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'position_id',
        'text',
        'sort_order',
        'evaluation_instructions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Question $question): void {
            if ($question->sort_order !== null || $question->position_id === null) {
                return;
            }

            $maxSortOrder = static::query()
                ->where('position_id', $question->position_id)
                ->max('sort_order');

            $question->sort_order = $maxSortOrder + 1;
        });
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
