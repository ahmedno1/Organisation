<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BenefitController extends Controller
{
    public function index(): View
    {
        $benefits = Benefit::query()->orderBy('name')->paginate(20);

        return view('benefits.index', compact('benefits'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'benefit_date' => ['nullable', 'date'],
            'supporter_name' => ['nullable', 'string', 'max:255'],
            'target_category' => ['nullable', 'string', 'max:255'],
        ]);

        $normalizedSupporter = isset($data['supporter_name']) ? trim((string) $data['supporter_name']) : null;
        $normalizedSupporter = $normalizedSupporter !== '' ? $normalizedSupporter : null;
        $normalizedDate = ! empty($data['benefit_date']) ? (string) $data['benefit_date'] : null;
        $uniqueRule = Rule::unique('benefits', 'name')
            ->where(function ($query) use ($normalizedSupporter, $normalizedDate): void {
                $query->where('supporter_name', $normalizedSupporter);
                if ($normalizedDate === null) {
                    $query->whereNull('benefit_date');
                } else {
                    $query->whereDate('benefit_date', $normalizedDate);
                }
            });
        $request->validate([
            'name' => ['required', 'string', 'max:255', $uniqueRule],
        ]);

        Benefit::create([
            'name' => trim($data['name']),
            'benefit_date' => $data['benefit_date'] ?? null,
            'supporter_name' => $normalizedSupporter,
            'target_category' => isset($data['target_category']) ? trim($data['target_category']) : null,
        ]);
        ActivityLogger::log($request, 'benefit.created');

        return redirect()->route('benefits.index')->with('status', __('ui.benefit_created'));
    }

    public function update(Request $request, Benefit $benefit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'benefit_date' => ['nullable', 'date'],
            'supporter_name' => ['nullable', 'string', 'max:255'],
            'target_category' => ['nullable', 'string', 'max:255'],
        ]);

        $normalizedSupporter = isset($data['supporter_name']) ? trim((string) $data['supporter_name']) : null;
        $normalizedSupporter = $normalizedSupporter !== '' ? $normalizedSupporter : null;
        $normalizedDate = ! empty($data['benefit_date']) ? (string) $data['benefit_date'] : null;
        $uniqueRule = Rule::unique('benefits', 'name')
            ->ignore($benefit->id)
            ->where(function ($query) use ($normalizedSupporter, $normalizedDate): void {
                $query->where('supporter_name', $normalizedSupporter);
                if ($normalizedDate === null) {
                    $query->whereNull('benefit_date');
                } else {
                    $query->whereDate('benefit_date', $normalizedDate);
                }
            });
        $request->validate([
            'name' => ['required', 'string', 'max:255', $uniqueRule],
        ]);

        $benefit->update([
            'name' => trim($data['name']),
            'benefit_date' => $data['benefit_date'] ?? null,
            'supporter_name' => $normalizedSupporter,
            'target_category' => isset($data['target_category']) ? trim($data['target_category']) : null,
        ]);
        ActivityLogger::log($request, 'benefit.updated', $benefit);

        return redirect()->route('benefits.index')->with('status', __('ui.benefit_updated'));
    }

    public function destroy(Request $request, Benefit $benefit): RedirectResponse
    {
        ActivityLogger::log($request, 'benefit.deleted', $benefit);
        $benefit->delete();

        return redirect()->route('benefits.index')->with('status', __('ui.benefit_deleted'));
    }
}
