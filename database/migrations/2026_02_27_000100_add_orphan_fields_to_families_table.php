<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            if (! Schema::hasColumn('families', 'mother_name')) {
                $table->string('mother_name')->nullable()->after('spouse_pregnant');
            }
            if (! Schema::hasColumn('families', 'mother_national_id')) {
                $table->string('mother_national_id', 30)->nullable()->after('mother_name');
            }
            if (! Schema::hasColumn('families', 'mother_marital_status')) {
                $table->string('mother_marital_status')->nullable()->after('mother_national_id');
            }
            if (! Schema::hasColumn('families', 'guardian_name')) {
                $table->string('guardian_name')->nullable()->after('mother_marital_status');
            }
            if (! Schema::hasColumn('families', 'guardian_national_id')) {
                $table->string('guardian_national_id', 30)->nullable()->after('guardian_name');
            }
            if (! Schema::hasColumn('families', 'guardian_mobile')) {
                $table->string('guardian_mobile', 25)->nullable()->after('guardian_national_id');
            }
            if (! Schema::hasColumn('families', 'siblings_count')) {
                $table->unsignedSmallInteger('siblings_count')->nullable()->after('guardian_mobile');
            }
            if (! Schema::hasColumn('families', 'father_death_date')) {
                $table->date('father_death_date')->nullable()->after('siblings_count');
            }
            if (! Schema::hasColumn('families', 'mother_death_date')) {
                $table->date('mother_death_date')->nullable()->after('father_death_date');
            }
            if (! Schema::hasColumn('families', 'education_status')) {
                $table->string('education_status')->nullable()->after('mother_death_date');
            }
            if (! Schema::hasColumn('families', 'detailed_address')) {
                $table->text('detailed_address')->nullable()->after('education_status');
            }
            if (! Schema::hasColumn('families', 'contact_phones')) {
                $table->text('contact_phones')->nullable()->after('detailed_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            foreach ([
                'contact_phones',
                'detailed_address',
                'education_status',
                'mother_death_date',
                'father_death_date',
                'siblings_count',
                'guardian_mobile',
                'guardian_national_id',
                'guardian_name',
                'mother_marital_status',
                'mother_national_id',
                'mother_name',
            ] as $column) {
                if (Schema::hasColumn('families', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
