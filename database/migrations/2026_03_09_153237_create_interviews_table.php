<?php

use App\Enums\InterviewStatus;
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
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->enum('status', array_column(InterviewStatus::cases(), 'value'))
                ->default(InterviewStatus::Pending->value);
            $table->decimal('score', 4, 2)->nullable();
            $table->unsignedTinyInteger('candidate_feedback_rating')->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('position_id');
            $table->index('status');
            $table->index('email');
        });

        DB::statement('ALTER TABLE interviews ADD CONSTRAINT interviews_score_check CHECK (score IS NULL OR (score >= 1 AND score <= 10))');
        DB::statement('ALTER TABLE interviews ADD CONSTRAINT interviews_candidate_feedback_rating_check CHECK (candidate_feedback_rating IS NULL OR (candidate_feedback_rating >= 1 AND candidate_feedback_rating <= 5))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
