<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benefits', function (Blueprint $table): void {
            $table->date('benefit_date')->nullable()->after('name');
            $table->string('supporter_name')->nullable()->after('benefit_date');
            $table->string('target_category')->nullable()->after('supporter_name');
        });
    }

    public function down(): void
    {
        Schema::table('benefits', function (Blueprint $table): void {
            $table->dropColumn(['benefit_date', 'supporter_name', 'target_category']);
        });
    }
};
