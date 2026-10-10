<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benefits', function (Blueprint $table): void {
            $table->dropUnique('benefits_name_unique');
            $table->unique(['name', 'supporter_name'], 'benefits_name_supporter_unique');
        });
    }

    public function down(): void
    {
        Schema::table('benefits', function (Blueprint $table): void {
            $table->dropUnique('benefits_name_supporter_unique');
            $table->unique('name', 'benefits_name_unique');
        });
    }
};
