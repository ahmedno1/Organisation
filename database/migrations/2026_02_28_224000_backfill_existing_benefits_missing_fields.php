<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('benefits')) {
            return;
        }

        // Fill missing supporter name
        DB::table('benefits')
            ->where(function ($query): void {
                $query->whereNull('supporter_name')
                    ->orWhereRaw('TRIM(supporter_name) = ?', ['']);
            })
            ->update(['supporter_name' => 'غير محدد']);

        // Fill missing target category
        DB::table('benefits')
            ->where(function ($query): void {
                $query->whereNull('target_category')
                    ->orWhereRaw('TRIM(target_category) = ?', ['']);
            })
            ->update(['target_category' => 'أسرة']);

        // Fill missing benefit date from created_at date, fallback to today
        $rows = DB::table('benefits')
            ->select('id', 'created_at')
            ->whereNull('benefit_date')
            ->get();

        foreach ($rows as $row) {
            $date = null;
            if (! empty($row->created_at)) {
                $date = date('Y-m-d', strtotime((string) $row->created_at));
            }
            if (! $date) {
                $date = date('Y-m-d');
            }

            DB::table('benefits')
                ->where('id', $row->id)
                ->update(['benefit_date' => $date]);
        }
    }

    public function down(): void
    {
        // Keep backfilled historical values.
    }
};

