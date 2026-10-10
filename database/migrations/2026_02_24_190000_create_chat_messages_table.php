<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['sender_id', 'receiver_id', 'created_at'], 'chat_messages_sender_receiver_created_idx');
            $table->index(['receiver_id', 'read_at'], 'chat_messages_receiver_read_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
