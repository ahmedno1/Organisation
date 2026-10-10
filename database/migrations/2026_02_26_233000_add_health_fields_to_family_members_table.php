<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('family_members', 'chronic_diseases')) {
                $table->text('chronic_diseases')->nullable()->after('member_age');
            }
            if (! Schema::hasColumn('family_members', 'disability')) {
                $table->text('disability')->nullable()->after('chronic_diseases');
            }
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            if (Schema::hasColumn('family_members', 'disability')) {
                $table->dropColumn('disability');
            }
            if (Schema::hasColumn('family_members', 'chronic_diseases')) {
                $table->dropColumn('chronic_diseases');
            }
        });
    }
};
