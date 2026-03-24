<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewQuestion extends Model
{
    /** @use HasFactory<\Database\Factories\InterviewQuestionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'interview_id',
        'question_id',
        'question_text',
        'evaluation_instructions_snapshot',
        'sort_order',
        'candidate_answer',
        'candidate_answer_audio_path',
        'ai_comment',
        'answer_score',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interview_id' => 'integer',
            'question_id' => 'integer',
            'sort_order' => 'integer',
            'answer_score' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InterviewQuestion $interviewQuestion): void {
            if ($interviewQuestion->sort_order !== null || $interviewQuestion->interview_id === null) {
                return;
            }

            $maxSortOrder = static::query()
                ->where('interview_id', $interviewQuestion->interview_id)
                ->max('sort_order');

            $interviewQuestion->sort_order = $maxSortOrder + 1;
        });

        static::saved(function (InterviewQuestion $interviewQuestion): void {
            $interviewQuestion->interview?->syncScoreFromAnswers();
        });

        static::deleted(function (InterviewQuestion $interviewQuestion): void {
            $interviewQuestion->interview?->syncScoreFromAnswers();
        });
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
