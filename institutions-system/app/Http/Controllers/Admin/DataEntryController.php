<?php

namespace App\Http\Controllers\Admin;

use App\Models\DataEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DataEntryController extends Controller
{
    public function index(): View
    {
        $dataEntries = DataEntry::query()
            ->where('administrator_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('admin.data_entries.index', compact('dataEntries'));
    }

    public function create(): View
    {
        return view('admin.data_entries.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'data' => 'nullable|string',
        ]);

        DataEntry::query()->create([
            'administrator_id' => auth()->id(),
            'name' => $validated['name'],
            'data' => $validated['data'] ?? null,
        ]);

        return redirect()->route('data_entries.index')->with('success', 'Data entry personnel added successfully.');
    }

    public function edit(DataEntry $dataEntry): View
    {
        $this->authorizeDataEntry($dataEntry);

        return view('admin.data_entries.edit', compact('dataEntry'));
    }

    public function update(Request $request, DataEntry $dataEntry): RedirectResponse
    {
        $this->authorizeDataEntry($dataEntry);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'data' => 'nullable|string',
        ]);

        $dataEntry->update($validated);

        return redirect()->route('data_entries.index')->with('success', 'Data entry personnel updated successfully.');
    }

    public function destroy(DataEntry $dataEntry): RedirectResponse
    {
        $this->authorizeDataEntry($dataEntry);
        $dataEntry->delete();

        return redirect()->route('data_entries.index')->with('success', 'Data entry personnel deleted successfully.');
    }

    private function authorizeDataEntry(DataEntry $dataEntry): void
    {
        abort_unless((int) $dataEntry->administrator_id === (int) auth()->id(), 403);
    }
}
