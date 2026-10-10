<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->string('head_gender', 10)->nullable()->after('head_national_id');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->dropColumn('head_gender');
        });
    }
};
