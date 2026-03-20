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
        DB::statement("UPDATE interviews SET status = 'pending_interview' WHERE status = 'pending'");
        DB::statement("UPDATE interviews SET status = 'reviewed_passed' WHERE status = 'passed'");
        DB::statement("UPDATE interviews SET status = 'reviewed_failed' WHERE status = 'failed'");
        DB::statement(
            "ALTER TABLE interviews MODIFY status ENUM('pending_confirmation','pending_interview','in_progress','completed','queued_for_review','reviewing','reviewed_passed','reviewed_failed','review_failed') NOT NULL DEFAULT 'pending_confirmation'",
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE interviews SET status = 'pending' WHERE status IN ('pending_confirmation', 'pending_interview', 'in_progress', 'queued_for_review', 'reviewing', 'review_failed')");
        DB::statement("UPDATE interviews SET status = 'passed' WHERE status = 'reviewed_passed'");
        DB::statement("UPDATE interviews SET status = 'failed' WHERE status = 'reviewed_failed'");
        DB::statement(
            "ALTER TABLE interviews MODIFY status ENUM('pending','completed','passed','failed') NOT NULL DEFAULT 'pending'",
        );
    }
};
