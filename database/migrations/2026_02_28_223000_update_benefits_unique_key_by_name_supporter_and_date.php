<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS benefits_name_supporter_unique');
        DB::statement('DROP INDEX IF EXISTS benefits_name_unique');

        Schema::table('benefits', function (Blueprint $table): void {
            $table->unique(
                ['name', 'supporter_name', 'benefit_date'],
                'benefits_name_supporter_date_unique'
            );
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS benefits_name_supporter_date_unique');

        Schema::table('benefits', function (Blueprint $table): void {
            $table->unique(['name', 'supporter_name'], 'benefits_name_supporter_unique');
        });
    }
};
