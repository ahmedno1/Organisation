<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\Benefit;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('dashboard');
    }

    public function printReport(Request $request): View
    {
        $user = $request->user();
        $familiesQuery = $this->scopedFamilies($user);
        $widowStatus = $this->normalizeWidowStatus($request->input('widow_status'));
        $familiesQuery->whereIn('marital_status', $this->widowedStatusesForFilter($widowStatus));

        $families = $familiesQuery->with(['institution'])->withCount('members')->latest()->get();

        return view('reports.print', compact('families'));
    }

    public function exportFamilies(Request $request): StreamedResponse
    {
        ActivityLogger::log($request, 'report.families_export');
        $families = $this->scopedFamilies($request->user())
            ->with(['institution', 'administrator', 'dataEntry', 'members'])
            ->whereNotIn('marital_status', $this->orphanStatuses())
            ->orderByDesc('id')
            ->get();

        return response()->streamDownload(function () use ($families): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                __('ui.csv_head_name'),
                __('ui.csv_head_national_id'),
                __('ui.csv_head_birth_date'),
                __('ui.csv_marital_status'),
                __('ui.csv_spouse_name'),
                __('ui.csv_spouse_national_id'),
                __('ui.csv_spouse_birth_date'),
                __('ui.csv_governorate'),
                __('ui.csv_area'),
                __('ui.csv_members_count'),
                __('ui.csv_mobile'),
                __('ui.csv_war_injury'),
                __('ui.csv_chronic_diseases'),
                __('ui.csv_disability'),
                __('ui.csv_employment_status'),
                __('ui.csv_notes'),
                __('ui.csv_head_age'),
                __('ui.csv_spouse_age'),
                __('ui.csv_family_benefits'),
                __('ui.csv_institution'),
                __('ui.csv_administrator'),
                __('ui.csv_data_entry'),
                __('ui.csv_registered_members_count'),
            ]);

            foreach ($families as $family) {
                fputcsv($handle, [
                    $family->head_name,
                    $family->head_national_id,
                    optional($family->head_birth_date)->format('Y-m-d'),
                    $family->marital_status,
                    $family->spouse_name,
                    $family->spouse_national_id,
                    optional($family->spouse_birth_date)->format('Y-m-d'),
                    $family->governorate,
                    $family->area,
                    $family->family_members_count,
                    $family->mobile,
                    $family->war_injury ? __('ui.yes') : __('ui.no'),
                    $family->chronic_diseases,
                    $family->disability,
                    $family->employment_status,
                    $family->notes,
                    $family->head_age,
                    $family->spouse_age,
                    $family->benefits_from_institution,
                    $family->institution?->name,
                    $family->administrator?->name,
                    $family->dataEntry?->name,
                    $family->family_members_count,
                ]);
            }

            fclose($handle);
        }, 'families_export_'.now()->format('Ymd_His').'.csv');
    }

    public function exportOrphans(Request $request): StreamedResponse
    {
        ActivityLogger::log($request, 'report.orphans_export');
        $orphans = $this->scopedOrphans($request->user())
            ->with(['institution', 'administrator', 'dataEntry'])
            ->orderByDesc('id')
            ->get();

        $benefitIds = $orphans
            ->flatMap(function (Orphan $orphan): array {
                $memberPayload = collect($orphan->members_payload ?? [])->first() ?? [];

                return collect($memberPayload['benefit_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->all();
            })
            ->unique()
            ->values()
            ->all();

        $benefitsById = Benefit::query()
            ->whereIn('id', $benefitIds)
            ->get()
            ->keyBy('id');

        return response()->streamDownload(function () use ($orphans, $benefitsById): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'اسم اليتيم',
                'رقم هوية اليتيم',
                'تاريخ ميلاد اليتيم',
                'مكان ميلاد اليتيم',
                'الحالة الصحية لليتيم',
                'جوال اليتيم',
                'الجنس',
                'المحافظة',
                'المنطقة',
                'الأمراض المزمنة لليتيم',
                'الإعاقات لليتيم',
                'استفادات اليتيم',
                'اسم الوصي',
                'رقم هوية الوصي',
                'جوال الوصي',
                'صلة القرابة',
                'عدد الإخوة',
                'عدد أفراد الأسرة (محسوب)',
                'تاريخ وفاة الأب',
                'تاريخ وفاة الأم (إن كانت متوفاة)',
                'الحالة الدراسية',
                'صورة اليتيم (بالاسم الرباعي)',
                'شهادة الوفاة (أوراق ثبوتية)',
                'باقي الأوراق الثبوتية',
                'ملاحظات اليتيم',
                'ملاحظات عامة',
                'المؤسسة',
                'المسؤول',
                'مدخل البيانات',
            ]);

            foreach ($orphans as $orphan) {
                $memberPayload = collect($orphan->members_payload ?? [])->first() ?? [];

                $benefitItems = collect($memberPayload['benefit_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->map(function (int $id) use ($benefitsById): ?string {
                        /** @var Benefit|null $benefit */
                        $benefit = $benefitsById->get($id);
                        if (! $benefit) {
                            return null;
                        }

                        return sprintf(
                            '%s | داعم: %s | %s | %s',
                            (string) $benefit->name,
                            trim((string) ($benefit->supporter_name ?? '')) !== '' ? (string) $benefit->supporter_name : __('ui.not_specified'),
                            optional($benefit->benefit_date)->format('Y-m-d') ?: '-',
                            trim((string) ($benefit->target_category ?? '')) !== '' ? (string) $benefit->target_category : __('ui.not_specified')
                        );
                    })
                    ->filter()
                    ->unique()
                    ->implode("\n");

                $supportingDocuments = collect($orphan->supporting_documents ?? [])
                    ->map(fn ($doc) => trim((string) ($doc['name'] ?? '')))
                    ->filter()
                    ->implode(' | ');

                $orphanNotes = trim((string) ($memberPayload['notes'] ?? ''));
                $guardianRelation = match ((string) ($orphan->guardian_relation ?? '')) {
                    'mother' => 'الأم',
                    'grandfather' => 'الجد',
                    'grandmother' => 'الجدة',
                    'uncle' => 'العم',
                    'maternal_uncle' => 'الخال',
                    'brother' => 'الأخ',
                    'sister' => 'الأخت',
                    'other' => 'أخرى',
                    default => '',
                };

                fputcsv($handle, [
                    $orphan->head_name,
                    $orphan->head_national_id,
                    optional($orphan->head_birth_date)->format('m/d/Y'),
                    $memberPayload['birth_place'] ?? null,
                    $memberPayload['health_status'] ?? null,
                    $orphan->mobile,
                    match ((string) ($orphan->head_gender ?? '')) {
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                        default => '',
                    },
                    $orphan->governorate,
                    $orphan->area,
                    $orphan->chronic_diseases,
                    $orphan->disability,
                    $benefitItems,
                    $orphan->guardian_name,
                    $orphan->guardian_national_id,
                    $orphan->guardian_mobile,
                    $guardianRelation,
                    $orphan->siblings_count,
                    $orphan->family_members_count,
                    optional($orphan->father_death_date)->format('m/d/Y'),
                    optional($orphan->mother_death_date)->format('m/d/Y'),
                    $orphan->education_status,
                    basename((string) ($orphan->orphan_photo_path ?? '')),
                    basename((string) ($orphan->death_certificate_path ?? '')),
                    $supportingDocuments,
                    $orphanNotes !== '' ? $orphanNotes : null,
                    $orphan->notes,
                    $orphan->institution?->name,
                    $orphan->administrator?->name,
                    $orphan->dataEntry?->name,
                ]);
            }

            fclose($handle);
        }, 'orphans_export_'.now()->format('Ymd_His').'.csv');
    }

    public function exportStatistics(Request $request): StreamedResponse
    {
        ActivityLogger::log($request, 'report.statistics_export');
        $user = $request->user();
        $familiesQuery = $this->scopedFamilies($user);
        $orphansQuery = $this->scopedOrphans($user);
        $maritalStatus = (clone $familiesQuery)
            ->selectRaw('marital_status, count(*) as total')
            ->groupBy('marital_status')
            ->pluck('total', 'marital_status')
            ->toArray();

        $summary = [
            [__('ui.metric'), __('ui.value')],
            [__('ui.total_families'), (string) (clone $familiesQuery)->count()],
            [__('ui.orphans_count'), (string) (clone $orphansQuery)->count()],
            [__('ui.total_registered_family_members'), (string) (clone $familiesQuery)->sum('family_members_count')],
            [__('ui.war_injuries'), (string) (clone $familiesQuery)->where('war_injury', true)->count()],
            [__('ui.chronic_diseases_count'), (string) (clone $familiesQuery)->whereNotNull('chronic_diseases')->where('chronic_diseases', '!=', '')->count()],
        ];

        return response()->streamDownload(function () use ($summary, $maritalStatus): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            foreach ($summary as $row) {
                fputcsv($handle, $row);
            }

            fputcsv($handle, []);
            fputcsv($handle, [__('ui.marital_distribution'), __('ui.chart_count')]);

            foreach ($maritalStatus as $status => $total) {
                fputcsv($handle, [$status ?: __('ui.not_specified'), $total]);
            }

            fclose($handle);
        }, 'statistics_export_'.now()->format('Ymd_His').'.csv');
    }

    public function exportWidowsByStatus(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'widow_status' => ['nullable', Rule::in($this->widowedStatusesForValidation())],
        ]);

        $selectedWidowStatus = $this->normalizeWidowStatus($data['widow_status'] ?? null);
        $families = $this->scopedFamilies($request->user())
            ->with(['institution'])
            ->withCount('members')
            ->whereIn('marital_status', $this->widowedStatusesForFilter($selectedWidowStatus))
            ->orderByDesc('id')
            ->get();

        ActivityLogger::log($request, 'report.widows_export', null, [
            'status' => $selectedWidowStatus,
            'count' => $families->count(),
        ]);

        return response()->streamDownload(function () use ($families): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                __('ui.csv_head_name'),
                __('ui.csv_head_national_id'),
                __('ui.csv_marital_status'),
                __('ui.csv_family_members_count'),
                __('ui.csv_registered_children_count'),
                __('ui.csv_mobile'),
                __('ui.csv_governorate'),
                __('ui.csv_area'),
                __('ui.csv_institution'),
            ]);

            foreach ($families as $family) {
                fputcsv($handle, [
                    $family->head_name,
                    $family->head_national_id,
                    $family->marital_status,
                    $family->family_members_count,
                    $family->members_count,
                    $family->mobile,
                    $family->governorate,
                    $family->area,
                    $family->institution?->name,
                ]);
            }

            fclose($handle);
        }, 'widows_and_children_'.now()->format('Ymd_His').'.csv');
    }

    /**
     * @return array<int, string>
     */
    private function widowedStatusesCanonical(): array
    {
        return ['ارمل', 'ارملة'];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function legacyWidowStatusAliases(): array
    {
        return [
            'ارمل' => ['ط§ط±ظ…ظ„'],
            'ارملة' => ['ط§ط±ظ…ظ„ط©'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function widowedStatusesForValidation(): array
    {
        return collect($this->legacyWidowStatusAliases())
            ->flatMap(fn (array $legacy, string $canonical) => [$canonical, ...$legacy])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function widowedStatusesForFilter(?string $selectedCanonical): array
    {
        if (! $selectedCanonical) {
            return $this->widowedStatusesForValidation();
        }

        return [
            $selectedCanonical,
            ...($this->legacyWidowStatusAliases()[$selectedCanonical] ?? []),
        ];
    }

    private function normalizeWidowStatus(mixed $status): ?string
    {
        $value = trim((string) $status);

        if ($value === '') {
            return null;
        }

        if (in_array($value, $this->widowedStatusesCanonical(), true)) {
            return $value;
        }

        foreach ($this->legacyWidowStatusAliases() as $canonical => $legacyValues) {
            if (in_array($value, $legacyValues, true)) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function orphanStatuses(): array
    {
        return ['يتيم', 'يتيمة', 'ظٹطھظٹظ…', 'ط¸ظ¹ط·ع¾ط¸ظ¹ط¸â€¦'];
    }

    private function scopedFamilies(User $user)
    {
        if ($user->isDeveloper()) {
            return Family::query();
        }

        return Family::query()->where('institution_id', $user->institution_id);
    }

    private function scopedOrphans(User $user)
    {
        if ($user->isDeveloper()) {
            return Orphan::query();
        }

        return Orphan::query()->where('institution_id', $user->institution_id);
    }
}



