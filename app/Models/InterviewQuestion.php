<?php

namespace App\Models;

use App\Enums\QuestionAnswerMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'parent_question_id',
        'question_text',
        'evaluation_instructions_snapshot',
        'answer_mode',
        'sort_order',
        'candidate_answer',
        'candidate_answer_audio_path',
        'ai_comment',
        'answer_score',
        'adequacy_score',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interview_id' => 'integer',
            'question_id' => 'integer',
            'parent_question_id' => 'integer',
            'answer_mode' => QuestionAnswerMode::class,
            'sort_order' => 'integer',
            'answer_score' => 'decimal:2',
            'adequacy_score' => 'decimal:2',
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
            $interviewQuestion->interview?->syncAdequacyScoreFromAnswers();
        });

        static::deleted(function (InterviewQuestion $interviewQuestion): void {
            $interviewQuestion->interview?->syncScoreFromAnswers();
            $interviewQuestion->interview?->syncAdequacyScoreFromAnswers();
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

    public function parentQuestion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_question_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(self::class, 'parent_question_id')->orderBy('id');
    }

    public function isFollowUp(): bool
    {
        return $this->parent_question_id !== null;
    }

    public function isVoiceMode(): bool
    {
        return $this->answer_mode === QuestionAnswerMode::Voice;
    }

    public function hasAudioRecording(): bool
    {
        return $this->candidate_answer_audio_path !== null;
    }

    public function resolveRootQuestionId(): int
    {
        return $this->parent_question_id ?? $this->id;
    }
}
