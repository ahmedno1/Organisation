<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SecurityController extends Controller
{
    public function editPassword(): View
    {
        return view('developer.security.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()?->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();
        $changed = false;

        if ($user->email !== $data['email']) {
            $user->email = $data['email'];
            $changed = true;
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $changed = true;
        }

        if (! $changed) {
            return back()->with('status', __('ui.no_changes_applied'));
        }

        $user->save();
        ActivityLogger::log($request, 'security.account_updated_self');

        return back()->with('status', __('ui.account_credentials_updated_successfully'));
    }
}
