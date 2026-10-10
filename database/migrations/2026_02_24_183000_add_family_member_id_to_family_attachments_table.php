<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_attachments', function (Blueprint $table): void {
            $table->foreignId('family_member_id')
                ->nullable()
                ->after('family_id')
                ->constrained('family_members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('family_attachments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('family_member_id');
        });
    }
};
