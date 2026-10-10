<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('institutions', 'storage_quota_mb')) {
            Schema::table('institutions', function (Blueprint $table): void {
                $table->unsignedInteger('storage_quota_mb')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('institutions', 'storage_quota_mb')) {
            Schema::table('institutions', function (Blueprint $table): void {
                $table->dropColumn('storage_quota_mb');
            });
        }
    }
};
