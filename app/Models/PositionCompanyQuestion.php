<?php

namespace App\Models;

use App\Models\Concerns\AssignsSortOrderOnCreate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionCompanyQuestion extends Model
{
    /** @use HasFactory<\Database\Factories\PositionCompanyQuestionFactory> */
    use AssignsSortOrderOnCreate, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'position_id',
        'question',
        'answer',
        'sort_order',
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

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
