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
        Schema::table('questions', function (Blueprint $table) {
            $table->index('position_id', 'questions_position_id_index');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropUnique('questions_position_id_sort_order_unique');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->index(['position_id', 'sort_order'], 'questions_position_id_sort_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_position_id_sort_order_index');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->unique(['position_id', 'sort_order'], 'questions_position_id_sort_order_unique');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_position_id_index');
        });
    }
};
