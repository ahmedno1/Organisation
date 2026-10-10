<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('family_members', 'birth_place')) {
                $table->string('birth_place')->nullable()->after('birth_date');
            }
            if (! Schema::hasColumn('family_members', 'health_status')) {
                $table->string('health_status')->nullable()->after('member_age');
            }
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            if (Schema::hasColumn('family_members', 'health_status')) {
                $table->dropColumn('health_status');
            }
            if (Schema::hasColumn('family_members', 'birth_place')) {
                $table->dropColumn('birth_place');
            }
        });
    }
};
