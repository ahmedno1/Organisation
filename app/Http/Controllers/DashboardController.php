<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Orphan;
use App\Models\User;
use App\Services\InstitutionStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(private readonly InstitutionStorageService $institutionStorageService)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $familiesQuery = Family::query();
        $orphansQuery = Orphan::query();

        if ($user->isAdmin()) {
            $familiesQuery->where('institution_id', $user->institution_id);
            $orphansQuery->where('institution_id', $user->institution_id);
        } elseif ($user->isDataEntry()) {
            $familiesQuery->where('institution_id', $user->institution_id);
            $orphansQuery->where('institution_id', $user->institution_id);
        }

        $stats = [
            'families' => (clone $familiesQuery)->count(),
            'orphans' => (clone $orphansQuery)->count(),
            'members' => (clone $familiesQuery)->sum('family_members_count'),
            'data_entry_users' => $user->isAdmin()
                ? User::where('created_by', $user->id)->where('role', User::ROLE_DATA_ENTRY)->count()
                : 0,
            'administrators' => $user->isDeveloper()
                ? User::where('role', User::ROLE_ADMIN)->count()
                : 0,
        ];

        $familiesCollection = (clone $familiesQuery)->get(['created_at', 'marital_status']);
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfDay();

        $familiesMonthly = $this->buildCurrentMonthDailyTrend((clone $familiesQuery), $monthStart, $monthEnd);
        $orphansMonthly = $this->buildCurrentMonthDailyTrend((clone $orphansQuery), $monthStart, $monthEnd);

        $maritalStats = $familiesCollection
            ->groupBy(fn ($f) => $f->marital_status ?: 'Unknown')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $alerts = [
            'stale_records' => (clone $familiesQuery)->where('updated_at', '<', now()->subDays(90))->count(),
            'missing_mobile' => (clone $familiesQuery)->where(function ($q): void {
                $q->whereNull('mobile')->orWhere('mobile', '');
            })->count(),
            'high_priority' => (clone $familiesQuery)
                ->where(function ($q): void {
                    $q->where('war_injury', true)
                        ->orWhereNotNull('disability')
                        ->orWhereNotNull('chronic_diseases')
                        ->orWhere('head_age', '<', 18)
                        ->orWhere('head_gender', 'female');
                })
                ->count(),
        ];

        $institutionStorage = null;
        $institution = $user->institution;
        if ($institution && ($user->isAdmin() || $user->isDataEntry())) {
            $usedBytes = $this->institutionStorageService->getUsedBytes($institution);
            $quotaMb = $institution->storage_quota_mb;
            $quotaBytes = $quotaMb !== null && $quotaMb > 0 ? ((int) $quotaMb * 1048576) : null;
            $usagePercent = $quotaBytes ? min(100, round(($usedBytes / $quotaBytes) * 100, 1)) : null;

            $institutionStorage = [
                'quota_mb' => $quotaMb !== null && $quotaMb > 0 ? number_format((float) $quotaMb, 0) : null,
                'used_mb' => number_format($usedBytes / 1048576, 2),
                'usage_percent' => $usagePercent,
            ];
        }

        return view('dashboard.index', compact('stats', 'familiesMonthly', 'orphansMonthly', 'maritalStats', 'alerts', 'institutionStorage'));
    }

    private function buildCurrentMonthDailyTrend($query, Carbon $monthStart, Carbon $monthEnd): array
    {
        $base = collect();
        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $base->put($day->format('Y-m-d'), 0);
        }

        $counts = $query
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get(['created_at'])
            ->groupBy(fn ($item) => optional($item->created_at)->format('Y-m-d'))
            ->map(fn ($group) => $group->count());

        return $base
            ->map(fn ($value, $date) => (int) ($counts[$date] ?? 0))
            ->toArray();
    }
}
