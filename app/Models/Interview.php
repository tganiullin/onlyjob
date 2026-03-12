<?php

namespace App\Models;

use App\Enums\InterviewStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interview extends Model
{
    /** @use HasFactory<\Database\Factories\InterviewFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'position_id',
        'first_name',
        'last_name',
        'email',
        'telegram',
        'phone',
        'status',
        'score',
        'candidate_feedback_rating',
        'summary',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position_id' => 'integer',
            'status' => InterviewStatus::class,
            'score' => 'decimal:2',
            'candidate_feedback_rating' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Interview $interview): void {
            $interview->snapshotPositionQuestions();
            $interview->syncScoreFromAnswers();
        });
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class)->withTrashed();
    }

    public function interviewQuestions(): HasMany
    {
        return $this->hasMany(InterviewQuestion::class);
    }

    public function snapshotPositionQuestions(): void
    {
        if ($this->position_id === null || $this->interviewQuestions()->exists()) {
            return;
        }

        $questions = Question::query()
            ->where('position_id', $this->position_id)
            ->orderBy('sort_order')
            ->get(['id', 'text', 'evaluation_instructions', 'sort_order']);

        if ($questions->isEmpty()) {
            return;
        }

        $this->interviewQuestions()->createMany(
            $questions->map(static function (Question $question): array {
                return [
                    'question_id' => $question->id,
                    'question_text' => $question->text,
                    'evaluation_instructions_snapshot' => $question->evaluation_instructions,
                    'sort_order' => $question->sort_order,
                ];
            })->all(),
        );
    }

    public function syncScoreFromAnswers(): void
    {
        $averageScore = $this->interviewQuestions()
            ->whereNotNull('answer_score')
            ->avg('answer_score');

        $this->forceFill([
            'score' => $averageScore === null ? null : round((float) $averageScore, 2),
        ])->saveQuietly();
    }

    /**
     * @param  array{first_name: string, last_name: string, telegram: string, email?: string|null, phone?: string|null}  $candidateData
     */
    public static function createPendingForCandidate(Position $position, array $candidateData): self
    {
        return static::query()->create([
            'position_id' => $position->id,
            'first_name' => $candidateData['first_name'],
            'last_name' => $candidateData['last_name'],
            'email' => $candidateData['email'] ?? null,
            'telegram' => $candidateData['telegram'],
            'phone' => $candidateData['phone'] ?? null,
            'status' => InterviewStatus::Pending,
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }
}
