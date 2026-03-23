<?php

namespace App\Models;

use App\Enums\InterviewIntegrityEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewIntegrityEvent extends Model
{
    /** @use HasFactory<\Database\Factories\InterviewIntegrityEventFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'interview_id',
        'interview_question_id',
        'event_type',
        'occurred_at',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interview_id' => 'integer',
            'interview_question_id' => 'integer',
            'event_type' => InterviewIntegrityEventType::class,
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function interviewQuestion(): BelongsTo
    {
        return $this->belongsTo(InterviewQuestion::class);
    }
}
