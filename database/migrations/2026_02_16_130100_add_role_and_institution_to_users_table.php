<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(User::ROLE_DATA_ENTRY)->after('password');
            $table->foreignId('institution_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('institution_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn('role');
        });
    }
};
