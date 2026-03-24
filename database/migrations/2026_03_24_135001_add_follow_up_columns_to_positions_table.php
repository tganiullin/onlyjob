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
        Schema::table('positions', function (Blueprint $table) {
            $table->boolean('follow_up_enabled')->default(false)->after('is_public');
            $table->decimal('follow_up_score_threshold', 4, 2)->default(4.00)->after('follow_up_enabled');
            $table->unsignedTinyInteger('max_follow_ups_per_question')->default(1)->after('follow_up_score_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn([
                'follow_up_enabled',
                'follow_up_score_threshold',
                'max_follow_ups_per_question',
            ]);
        });
    }
};
