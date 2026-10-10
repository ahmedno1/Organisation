<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('priority')->default('Medium');
            $table->string('impact')->default('Medium');
            $table->string('status')->default('Planned');
            $table->text('summary')->nullable();
            $table->date('target_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_items');
    }
};
