<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('relation')->nullable();
            $table->string('name');
            $table->string('national_id')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('mobile')->nullable();
            $table->unsignedSmallInteger('head_age')->nullable();
            $table->unsignedSmallInteger('member_age')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
