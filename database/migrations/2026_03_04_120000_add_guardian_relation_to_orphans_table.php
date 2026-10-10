<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orphans', 'guardian_relation')) {
            Schema::table('orphans', function (Blueprint $table): void {
                $table->string('guardian_relation', 50)->nullable()->after('guardian_mobile');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orphans', 'guardian_relation')) {
            Schema::table('orphans', function (Blueprint $table): void {
                $table->dropColumn('guardian_relation');
            });
        }
    }
};
