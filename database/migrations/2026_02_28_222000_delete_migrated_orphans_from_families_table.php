<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('families') || ! Schema::hasTable('orphans')) {
            return;
        }

        $orphanStatuses = ['يتيم', 'ظٹطھظٹظ…', 'ط¸ظ¹ط·ع¾ط¸ظ¹ط¸â€¦'];

        DB::table('families')
            ->whereIn('marital_status', $orphanStatuses)
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $family) {
                    $orphanQuery = DB::table('orphans')
                        ->where('head_national_id', $family->head_national_id);

                    if ($family->institution_id === null) {
                        $orphanQuery->whereNull('institution_id');
                    } else {
                        $orphanQuery->where('institution_id', $family->institution_id);
                    }

                    if ($orphanQuery->exists()) {
                        DB::table('families')
                            ->where('id', $family->id)
                            ->delete();
                    }
                }
            });
    }

    public function down(): void
    {
        // Intentionally left empty. Deleted old orphan rows are not restored.
    }
};

