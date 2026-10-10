<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('two_factor_code', 12)->nullable()->after('remember_token');
            $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
            $table->timestamp('locked_until')->nullable()->after('two_factor_expires_at');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('locked_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_code',
                'two_factor_expires_at',
                'locked_until',
                'failed_login_attempts',
            ]);
        });
    }
};
