<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE interviews MODIFY status ENUM('pending','completed','passed','failed') NOT NULL DEFAULT 'pending'",
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE interviews SET status = 'pending' WHERE status = 'completed'");
        DB::statement(
            "ALTER TABLE interviews MODIFY status ENUM('pending','passed','failed') NOT NULL DEFAULT 'pending'",
        );
    }
};
