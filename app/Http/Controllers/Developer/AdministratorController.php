<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AdministratorController extends Controller
{
    public function index(): View
    {
        $administrators = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->with('institution')
            ->withCount('dataEntryUsers')
            ->latest()
            ->paginate(12);

        return view('developer.administrators.index', compact('administrators'));
    }

    public function create(): View
    {
        $institutions = Institution::query()->orderBy('name')->get();

        return view('developer.administrators.create', compact('institutions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(10)->letters()->mixedCase()->numbers()->symbols()],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'institution_storage_quota_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'institution_logo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif,image/x-icon', 'max:5120'],
        ]);

        $institution = $this->resolveInstitution(
            $data['institution_id'] ?? null,
            $data['institution_name'] ?? null,
            isset($data['institution_storage_quota_mb']) ? (int) $data['institution_storage_quota_mb'] : null,
            $request->file('institution_logo')
        );

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);
        ActivityLogger::log($request, 'administrator.created');

        return redirect()->route('developers.administrators.index')->with('status', __('ui.administrator_created'));
    }

    public function edit(User $administrator): View
    {
        abort_if($administrator->role !== User::ROLE_ADMIN, 404);

        $institutions = Institution::query()->orderBy('name')->get();

        return view('developer.administrators.edit', compact('administrator', 'institutions'));
    }

    public function update(Request $request, User $administrator): RedirectResponse
    {
        abort_if($administrator->role !== User::ROLE_ADMIN, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($administrator->id)],
            'password' => ['nullable', Password::min(10)->letters()->mixedCase()->numbers()->symbols()],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'institution_storage_quota_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'institution_logo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif,image/x-icon', 'max:5120'],
        ]);

        $institution = $this->resolveInstitution(
            $data['institution_id'] ?? null,
            $data['institution_name'] ?? null,
            isset($data['institution_storage_quota_mb']) ? (int) $data['institution_storage_quota_mb'] : null,
            $request->file('institution_logo')
        );

        $administrator->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'institution_id' => $institution->id,
        ]);

        if (! empty($data['password'])) {
            $administrator->update(['password' => $data['password']]);
        }
        ActivityLogger::log($request, 'administrator.updated', $administrator);

        return redirect()->route('developers.administrators.index')->with('status', __('ui.administrator_updated'));
    }

    public function destroy(Request $request, User $administrator): RedirectResponse
    {
        abort_if($administrator->role !== User::ROLE_ADMIN, 404);

        $dataEntryIds = User::query()
            ->where('role', User::ROLE_DATA_ENTRY)
            ->where('created_by', $administrator->id)
            ->pluck('id');

        $administrator->adminFamilies()->delete();

        if ($dataEntryIds->isNotEmpty()) {
            User::whereIn('id', $dataEntryIds)->each(function (User $user): void {
                $user->enteredFamilies()->delete();
                $user->delete();
            });
        }

        $administrator->delete();
        ActivityLogger::log($request, 'administrator.deleted', $administrator);

        return redirect()->route('developers.administrators.index')->with('status', __('ui.administrator_deleted_with_data_entries'));
    }

    private function resolveInstitution(
        ?string $institutionId,
        ?string $institutionName,
        ?int $institutionStorageQuotaMb,
        ?UploadedFile $institutionLogo
    ): Institution
    {
        if (! empty($institutionId)) {
            $institution = Institution::findOrFail($institutionId);
        } else {
            if (empty($institutionName)) {
                throw ValidationException::withMessages([
                    'institution_name' => __('ui.institution_name_required'),
                ]);
            }

            $institution = Institution::firstOrCreate(['name' => trim($institutionName)]);
        }

        if ($institutionStorageQuotaMb !== null) {
            $institution->update([
                'storage_quota_mb' => $institutionStorageQuotaMb,
            ]);
        }

        if ($institutionLogo instanceof UploadedFile) {
            $this->storeInstitutionLogoCopy($institution, $institutionLogo);
        }

        return $institution;
    }

    private function storeInstitutionLogoCopy(Institution $institution, UploadedFile $institutionLogo): void
    {
        $disk = Storage::disk('public');
        $oldPath = $this->normalizeLogoPath((string) $institution->logo_path);
        if ($oldPath !== null && $disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }

        $extension = strtolower((string) ($institutionLogo->getClientOriginalExtension() ?: $institutionLogo->extension() ?: 'png'));
        $extension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'png';
        $directory = 'institution-logos/institution-'.$institution->id;
        $filename = 'logo_'.now()->format('Ymd_His').'_'.$institution->id.'_'.Str::random(6).'.'.$extension;
        $path = $institutionLogo->storeAs($directory, $filename, 'public');

        $institution->update([
            'logo_path' => $path,
        ]);
    }

    private function normalizeLogoPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        if (preg_match('#^https?://#i', $path) === 1) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsedPath) ? $parsedPath : $path;
        }

        $normalized = ltrim($path, '/');
        return preg_replace('#^(storage|public)/#i', '', $normalized) ?? $normalized;
    }
}
