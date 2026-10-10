<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->timestamp('delivered_at')->nullable()->after('body');
            $table->index(['receiver_id', 'delivered_at'], 'chat_messages_receiver_delivered_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropIndex('chat_messages_receiver_delivered_idx');
            $table->dropColumn('delivered_at');
        });
    }
};
