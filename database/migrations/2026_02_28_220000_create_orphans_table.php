<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orphans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('administrator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('data_entry_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('head_name');
            $table->string('head_national_id', 30);
            $table->string('head_gender')->nullable();
            $table->date('head_birth_date')->nullable();
            $table->string('marital_status')->default('يتيم');
            $table->string('guardian_name')->nullable();
            $table->string('guardian_national_id', 30)->nullable();
            $table->string('guardian_mobile', 25)->nullable();
            $table->unsignedSmallInteger('siblings_count')->nullable();
            $table->date('father_death_date')->nullable();
            $table->date('mother_death_date')->nullable();
            $table->string('education_status')->nullable();
            $table->text('detailed_address')->nullable();
            $table->text('contact_phones')->nullable();
            $table->string('governorate')->nullable();
            $table->string('area')->nullable();
            $table->unsignedInteger('family_members_count')->default(1);
            $table->string('mobile', 25)->nullable();
            $table->text('chronic_diseases')->nullable();
            $table->text('disability')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('head_age')->nullable();
            $table->string('orphan_photo_path')->nullable();
            $table->string('death_certificate_path')->nullable();
            $table->json('supporting_documents')->nullable();
            $table->json('members_payload')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'head_national_id'], 'orphans_institution_head_nid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orphans');
    }
};

