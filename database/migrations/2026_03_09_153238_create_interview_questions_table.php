<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interview_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question_text');
            $table->text('evaluation_instructions_snapshot')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->longText('candidate_answer')->nullable();
            $table->text('ai_comment')->nullable();
            $table->decimal('answer_score', 4, 2)->nullable();
            $table->timestamps();

            $table->index(['interview_id', 'question_id']);
            $table->index(['interview_id', 'sort_order']);
        });

        DB::statement('ALTER TABLE interview_questions ADD CONSTRAINT interview_questions_answer_score_check CHECK (answer_score IS NULL OR (answer_score >= 1 AND answer_score <= 10))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_questions');
    }
};
