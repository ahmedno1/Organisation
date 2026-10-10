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

        $families = DB::table('families')
            ->whereIn('marital_status', $orphanStatuses)
            ->orderBy('id')
            ->get();

        foreach ($families as $family) {
            $membersPayload = [];
            if (Schema::hasTable('family_members')) {
                $membersPayload = DB::table('family_members')
                    ->where('family_id', $family->id)
                    ->orderBy('id')
                    ->get([
                        'relation',
                        'name',
                        'national_id',
                        'birth_date',
                        'birth_place',
                        'mobile',
                        'member_age',
                        'health_status',
                        'chronic_diseases',
                        'disability',
                        'notes',
                    ])
                    ->map(fn ($member) => (array) $member)
                    ->values()
                    ->all();
            }

            $payload = [
                'administrator_id' => $family->administrator_id,
                'data_entry_id' => $family->data_entry_id,
                'head_name' => $family->head_name,
                'head_gender' => $family->head_gender,
                'head_birth_date' => $family->head_birth_date,
                'marital_status' => 'يتيم',
                'guardian_name' => $family->guardian_name,
                'guardian_national_id' => $family->guardian_national_id,
                'guardian_mobile' => $family->guardian_mobile,
                'siblings_count' => $family->siblings_count,
                'father_death_date' => $family->father_death_date,
                'mother_death_date' => $family->mother_death_date,
                'education_status' => $family->education_status,
                'detailed_address' => $family->detailed_address,
                'contact_phones' => $family->contact_phones,
                'governorate' => $family->governorate,
                'area' => $family->area,
                'family_members_count' => max(1, (int) ($family->family_members_count ?? 1)),
                'mobile' => $family->mobile,
                'chronic_diseases' => $family->chronic_diseases,
                'disability' => $family->disability,
                'notes' => $family->notes,
                'head_age' => $family->head_age,
                'members_payload' => json_encode($membersPayload, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            $existing = DB::table('orphans')
                ->where('institution_id', $family->institution_id)
                ->where('head_national_id', $family->head_national_id)
                ->first();

            if ($existing) {
                DB::table('orphans')
                    ->where('id', $existing->id)
                    ->update($payload);
            } else {
                DB::table('orphans')->insert(array_merge($payload, [
                    'institution_id' => $family->institution_id,
                    'head_national_id' => $family->head_national_id,
                    'created_at' => $family->created_at ?? now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        // Intentionally left empty to avoid deleting orphan data.
    }
};

