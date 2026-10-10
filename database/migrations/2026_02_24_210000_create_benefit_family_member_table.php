<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benefit_family_member', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_member_id')->constrained('family_members')->cascadeOnDelete();
            $table->foreignId('benefit_id')->constrained('benefits')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['family_member_id', 'benefit_id'], 'benefit_member_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_family_member');
    }
};
