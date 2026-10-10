<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('families')
            ->select('institution_id', 'head_national_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('institution_id')
            ->whereNotNull('head_national_id')
            ->where('head_national_id', '!=', '')
            ->groupBy('institution_id', 'head_national_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $sample = $duplicates
                ->map(fn ($row) => "institution_id={$row->institution_id}, head_national_id={$row->head_national_id}, count={$row->total}")
                ->implode(' | ');

            throw new \RuntimeException('Cannot add unique families constraint due to existing duplicates: '.$sample);
        }

        Schema::table('families', function ($table): void {
            $table->unique(['institution_id', 'head_national_id'], 'families_institution_head_national_unique');
        });
    }

    public function down(): void
    {
        Schema::table('families', function ($table): void {
            $table->dropUnique('families_institution_head_national_unique');
        });
    }
};
