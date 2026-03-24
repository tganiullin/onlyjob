<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('interview_questions', function (Blueprint $table) {
            $table->boolean('is_follow_up')->default(false)->after('sort_order');
            $table->foreignId('parent_interview_question_id')
                ->nullable()
                ->after('is_follow_up')
                ->constrained('interview_questions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('interview_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_interview_question_id');
            $table->dropColumn('is_follow_up');
        });
    }
};
