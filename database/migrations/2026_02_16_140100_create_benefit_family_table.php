<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benefit_family', function (Blueprint $table) {
            $table->id();
            $table->foreignId('benefit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['benefit_id', 'family_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_family');
    }
};
