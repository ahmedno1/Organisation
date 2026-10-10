<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('administrator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('data_entry_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('head_name');
            $table->string('head_national_id')->index();
            $table->date('head_birth_date')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('spouse_name')->nullable();
            $table->string('spouse_national_id')->nullable();
            $table->date('spouse_birth_date')->nullable();
            $table->string('governorate')->nullable();
            $table->string('area')->nullable();
            $table->unsignedInteger('family_members_count')->default(1);
            $table->string('mobile')->nullable();
            $table->boolean('war_injury')->default(false);
            $table->text('chronic_diseases')->nullable();
            $table->text('disability')->nullable();
            $table->string('employment_status')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('head_age')->nullable();
            $table->unsignedSmallInteger('spouse_age')->nullable();
            $table->text('benefits_from_institution')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
