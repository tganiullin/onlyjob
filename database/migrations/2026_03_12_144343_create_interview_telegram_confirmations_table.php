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
        Schema::create('interview_telegram_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interview_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('expected_username');

            $table->char('session_fingerprint', 64);
            $table->uuid('client_request_id');
            $table->char('status_token', 64)->unique();
            $table->char('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('superseded_at')->nullable();

            $table->bigInteger('telegram_user_id')->nullable();
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->bigInteger('telegram_update_id')->nullable()->unique();

            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index('position_id');
            $table->index('expected_username');
            $table->index('session_fingerprint');
            $table->index('client_request_id');
            $table->index('expires_at');
            $table->index('confirmed_at');
            $table->index('used_at');
            $table->index('superseded_at');
            $table->index('telegram_user_id');
            $table->index('telegram_chat_id');
            $table->index([
                'position_id',
                'session_fingerprint',
                'client_request_id',
            ], 'itc_position_session_client_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_telegram_confirmations');
    }
};
