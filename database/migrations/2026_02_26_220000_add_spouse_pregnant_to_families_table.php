<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            if (! Schema::hasColumn('families', 'spouse_pregnant')) {
                $table->boolean('spouse_pregnant')->nullable()->after('spouse_birth_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            if (Schema::hasColumn('families', 'spouse_pregnant')) {
                $table->dropColumn('spouse_pregnant');
            }
        });
    }
};
