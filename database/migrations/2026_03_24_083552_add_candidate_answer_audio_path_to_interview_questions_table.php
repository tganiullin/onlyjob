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
            $table->string('candidate_answer_audio_path')->nullable()->after('candidate_answer');
        });
    }

    public function down(): void
    {
        Schema::table('interview_questions', function (Blueprint $table) {
            $table->dropColumn('candidate_answer_audio_path');
        });
    }
};
