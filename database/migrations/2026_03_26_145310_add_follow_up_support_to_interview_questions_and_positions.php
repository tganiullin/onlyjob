<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interview_questions', function (Blueprint $table) {
            $table->foreignId('parent_question_id')
                ->nullable()
                ->after('question_id')
                ->constrained('interview_questions')
                ->cascadeOnDelete();
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->boolean('follow_up_enabled')->default(false)->after('is_public');
            $table->unsignedTinyInteger('max_follow_ups_per_question')->default(1)->after('follow_up_enabled');
            $table->unsignedTinyInteger('follow_up_min_score')->nullable()->after('max_follow_ups_per_question');
        });
    }

    public function down(): void
    {
        Schema::table('interview_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_question_id');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn(['follow_up_enabled', 'max_follow_ups_per_question', 'follow_up_min_score']);
        });
    }
};
