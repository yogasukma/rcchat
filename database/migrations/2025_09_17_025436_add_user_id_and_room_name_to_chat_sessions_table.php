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
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->string('user_id')->nullable()->after('app_key')->index();
            $table->string('room_name')->nullable()->after('room_id');
            $table->timestamp('last_activity')->nullable()->after('expires_at');
            
            // Add composite index for user room queries
            $table->index(['user_id', 'last_activity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'last_activity']);
            $table->dropIndex(['user_id']);
            $table->dropColumn(['user_id', 'room_name', 'last_activity']);
        });
    }
};