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
        Schema::table('interviews', function (Blueprint $table) {
            $table->timestamp('telegram_confirmed_at')->nullable()->after('telegram');
            $table->bigInteger('telegram_user_id')->nullable()->after('telegram_confirmed_at');
            $table->bigInteger('telegram_chat_id')->nullable()->after('telegram_user_id');
            $table->string('telegram_confirmed_username')->nullable()->after('telegram_chat_id');

            $table->index('telegram_confirmed_at');
            $table->index('telegram_user_id');
            $table->index('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->dropIndex(['telegram_confirmed_at']);
            $table->dropIndex(['telegram_user_id']);
            $table->dropIndex(['telegram_chat_id']);

            $table->dropColumn([
                'telegram_confirmed_at',
                'telegram_user_id',
                'telegram_chat_id',
                'telegram_confirmed_username',
            ]);
        });
    }
};
