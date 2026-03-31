<?php

namespace App\Models;

use App\Enums\QuestionAnswerMode;
use App\Models\Concerns\AssignsSortOrderOnCreate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    /** @use HasFactory<\Database\Factories\QuestionFactory> */
    use AssignsSortOrderOnCreate, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'position_id',
        'text',
        'sort_order',
        'evaluation_instructions',
        'answer_mode',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position_id' => 'integer',
            'sort_order' => 'integer',
            'answer_mode' => QuestionAnswerMode::class,
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
