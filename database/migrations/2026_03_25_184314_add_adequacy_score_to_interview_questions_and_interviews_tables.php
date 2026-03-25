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
        Schema::table('interview_questions', function (Blueprint $table) {
            $table->decimal('adequacy_score', 4, 2)->nullable()->after('answer_score');
        });

        DB::statement('ALTER TABLE interview_questions ADD CONSTRAINT interview_questions_adequacy_score_check CHECK (adequacy_score IS NULL OR (adequacy_score >= 1 AND adequacy_score <= 10))');

        Schema::table('interviews', function (Blueprint $table) {
            $table->decimal('adequacy_score', 4, 2)->nullable()->after('score');
        });

        DB::statement('ALTER TABLE interviews ADD CONSTRAINT interviews_adequacy_score_check CHECK (adequacy_score IS NULL OR (adequacy_score >= 1 AND adequacy_score <= 10))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interview_questions', function (Blueprint $table) {
            $table->dropColumn('adequacy_score');
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->dropColumn('adequacy_score');
        });
    }
};
