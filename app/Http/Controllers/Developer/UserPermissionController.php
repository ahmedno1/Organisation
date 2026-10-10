<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserPermissionController extends Controller
{
    public function edit(User $user): View
    {
        abort_if($user->isDeveloper(), 404);

        $permissionKeys = array_keys($user->defaultPermissions());
        $effectivePermissions = [];

        foreach ($permissionKeys as $key) {
            $effectivePermissions[$key] = $user->hasPermission($key);
        }

        return view('developer.permissions.edit', compact('user', 'permissionKeys', 'effectivePermissions'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isDeveloper(), 404);

        $permissionKeys = array_keys($user->defaultPermissions());
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
        ]);

        $overrides = [];
        foreach ($permissionKeys as $key) {
            if (array_key_exists($key, $validated['permissions'] ?? [])) {
                $overrides[$key] = (bool) ($validated['permissions'][$key] ?? false);
            }
        }

        $user->update(['permissions' => $overrides]);

        return redirect()
            ->route('developers.permissions.edit', $user)
            ->with('status', 'User permissions updated.');
    }
}
