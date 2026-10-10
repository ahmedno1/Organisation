<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class DataEntryUserController extends Controller
{
    public function index(Request $request): View
    {
        $dataEntryUsers = User::query()
            ->where('role', User::ROLE_DATA_ENTRY)
            ->where('created_by', $request->user()->id)
            ->latest()
            ->paginate(12);

        return view('admin.data_entry_users.index', compact('dataEntryUsers'));
    }

    public function create(): View
    {
        return view('admin.data_entry_users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(10)->letters()->mixedCase()->numbers()->symbols()],
            'permissions' => ['nullable', 'array'],
        ]);

        $admin = $request->user();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_DATA_ENTRY,
            'institution_id' => $admin->institution_id,
            'created_by' => $admin->id,
            'permissions' => $this->sanitizePermissions($data['permissions'] ?? []),
        ]);
        ActivityLogger::log($request, 'data_entry.created');

        return redirect()->route('admin.data-entry-users.index')->with('status', __('ui.data_entry_created'));
    }

    public function edit(Request $request, User $dataEntryUser): View
    {
        $this->ensureOwnedDataEntry($request->user(), $dataEntryUser);

        return view('admin.data_entry_users.edit', compact('dataEntryUser'));
    }

    public function update(Request $request, User $dataEntryUser): RedirectResponse
    {
        $this->ensureOwnedDataEntry($request->user(), $dataEntryUser);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($dataEntryUser->id)],
            'password' => ['nullable', Password::min(10)->letters()->mixedCase()->numbers()->symbols()],
            'permissions' => ['nullable', 'array'],
        ]);

        $dataEntryUser->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'institution_id' => $request->user()->institution_id,
            'permissions' => $this->sanitizePermissions($data['permissions'] ?? []),
        ]);

        if (! empty($data['password'])) {
            $dataEntryUser->update(['password' => $data['password']]);
        }
        ActivityLogger::log($request, 'data_entry.updated', $dataEntryUser);

        return redirect()->route('admin.data-entry-users.index')->with('status', __('ui.data_entry_updated'));
    }

    public function destroy(Request $request, User $dataEntryUser): RedirectResponse
    {
        $this->ensureOwnedDataEntry($request->user(), $dataEntryUser);

        $dataEntryUser->enteredFamilies()->delete();
        $dataEntryUser->delete();
        ActivityLogger::log($request, 'data_entry.deleted');

        return redirect()->route('admin.data-entry-users.index')->with('status', __('ui.data_entry_deleted'));
    }

    private function ensureOwnedDataEntry(User $admin, User $dataEntryUser): void
    {
        if (
            $dataEntryUser->role !== User::ROLE_DATA_ENTRY ||
            $dataEntryUser->created_by !== $admin->id
        ) {
            abort(404);
        }
    }

    private function sanitizePermissions(array $permissions): array
    {
        $allowed = [
            'manage_families',
            'view_all_families',
            'edit_families',
            'delete_families',
            'export_reports',
            'import_families',
        ];

        $sanitized = [];
        foreach ($allowed as $key) {
            $sanitized[$key] = (bool) ($permissions[$key] ?? false);
        }

        return $sanitized;
    }
}
