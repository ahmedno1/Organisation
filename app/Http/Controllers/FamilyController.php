<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\ActivityLog;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\User;
use App\Notifications\FamilyCreatedNotification;
use App\Support\ActivityLogger;
use App\Support\FamilyAccess;
use App\Services\InstitutionStorageService;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class FamilyController extends Controller
{
    public function __construct(private readonly InstitutionStorageService $institutionStorageService)
    {
    }

    public function index(Request $request): View
    {
        $query = $this->scopeNonOrphanFamilies($request->user());
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $families = $query->with(['institution', 'administrator', 'dataEntry', 'members'])->paginate(15);
        $families->appends($request->query());
        $smartAlerts = $this->buildSmartAlerts($this->scopeNonOrphanFamilies($request->user()));

        $filterOptions = [
            'institutions' => Institution::query()->orderBy('name')->get(['id', 'name']),
            'administrators' => User::query()->where('role', User::ROLE_ADMIN)->orderBy('name')->get(['id', 'name']),
            'dataEntries' => User::query()->where('role', User::ROLE_DATA_ENTRY)->orderBy('name')->get(['id', 'name']),
            'benefits' => Benefit::query()->orderBy('name')->get(['id', 'name']),
        ];

        return view('families.index', compact('families', 'filterOptions', 'smartAlerts'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        $institutions = $user->isDeveloper() ? Institution::orderBy('name')->get() : collect();
        $administrators = $user->isDeveloper()
            ? User::where('role', User::ROLE_ADMIN)->with('institution')->orderBy('name')->get()
            : collect();
        $benefits = Benefit::query()->orderBy('name')->get();

        return view('families.create', compact('user', 'institutions', 'administrators', 'benefits'));
    }

    public function orphansIndex(Request $request): View
    {
        $query = $this->scopeOrphans($request->user());

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($sub) use ($q): void {
                $sub->where('head_name', 'like', "%{$q}%")
                    ->orWhere('head_national_id', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%");
            });
        }

        if ($request->user()->isDeveloper() && $request->filled('institution_id')) {
            $query->where('institution_id', (int) $request->input('institution_id'));
        }

        $orphans = $query
            ->with(['institution', 'administrator', 'dataEntry'])
            ->latest()
            ->paginate(15);
        $orphans->appends($request->query());

        $institutions = $request->user()->isDeveloper()
            ? Institution::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('families.orphans', compact('orphans', 'institutions'));
    }

    public function createOrphan(Request $request): View
    {
        $user = $request->user();
        $institutions = $user->isDeveloper() ? Institution::query()->orderBy('name')->get() : collect();
        $administrators = $user->isDeveloper()
            ? User::query()->where('role', User::ROLE_ADMIN)->with('institution')->orderBy('name')->get()
            : collect();
        $benefits = Benefit::query()->orderBy('name')->get();
        $governorateOptions = config('family_options.palestine_governorates', []);
        $chronicOptions = config('family_options.chronic_diseases', []);
        $disabilityOptions = config('family_options.disabilities', []);

        return view('families.create_orphan', compact(
            'user',
            'institutions',
            'administrators',
            'benefits',
            'governorateOptions',
            'chronicOptions',
            'disabilityOptions'
        ));
    }

    public function storeOrphan(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rules = [
            'head_gender' => ['required', Rule::in(['male', 'female'])],
            'governorate' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_national_id' => ['required', 'regex:/^\d{9}$/'],
            'guardian_mobile' => ['required', 'regex:/^\d{9}$/'],
            'guardian_relation' => ['required', Rule::in(['mother', 'grandfather', 'grandmother', 'uncle', 'maternal_uncle', 'brother', 'sister', 'other'])],
            'siblings_count' => ['required', 'integer', 'min:0', 'max:50'],
            'father_death_date' => ['required', 'date'],
            'mother_death_date' => ['nullable', 'date'],
            'education_status' => ['required', 'string', 'max:255'],
            'detailed_address' => ['nullable', 'string'],
            'contact_phones' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'orphan_photo' => ['required', 'file', 'image', 'max:10240'],
            'death_certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'supporting_documents' => ['nullable', 'array'],
            'supporting_documents.*' => ['file', 'max:10240'],
            'members' => ['required', 'array', 'min:1'],
            'members.0.relation' => ['nullable', 'string', 'max:50'],
            'members.0.name' => ['required', 'string', 'max:255'],
            'members.0.national_id' => ['required', 'regex:/^\d{9}$/'],
            'members.0.birth_date' => ['required', 'date'],
            'members.0.birth_place' => ['required', 'string', 'max:255'],
            'members.0.health_status' => ['required', 'string', 'max:255'],
            'members.0.mobile' => ['nullable', 'regex:/^\d{9}$/'],
            'members.0.notes' => ['nullable', 'string'],
            'members.0.chronic_disease_items' => ['nullable', 'array'],
            'members.0.chronic_disease_items.*' => ['string', Rule::in(config('family_options.chronic_diseases', []))],
            'members.0.disability_items' => ['nullable', 'array'],
            'members.0.disability_items.*' => ['string', Rule::in(config('family_options.disabilities', []))],
            'members.0.attachments' => ['nullable', 'array'],
            'members.0.attachments.*' => ['file', 'max:10240'],
        ];

        if ($user->isDeveloper()) {
            $rules['institution_id'] = ['required', 'exists:institutions,id'];
            $rules['administrator_id'] = ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_ADMIN)];
        }

        $data = $request->validate($rules);
        $data['head_name'] = $data['members'][0]['name'];
        $data['head_national_id'] = $this->normalizeNationalId((string) $data['members'][0]['national_id']);
        $data['head_birth_date'] = $data['members'][0]['birth_date'];
        $data['head_age'] = $this->calculateAge($data['members'][0]['birth_date'] ?? null);
        $data['mobile'] = $data['members'][0]['mobile'] ?? null;
        if (! empty($data['guardian_national_id'])) {
            $data['guardian_national_id'] = $this->normalizeNationalId((string) $data['guardian_national_id']);
        }
        $familyMembersCount = max(
            1,
            1 + (int) ($data['siblings_count'] ?? 0) + (($data['guardian_relation'] ?? '') === 'mother' ? 1 : 0)
        );

        $institutionId = $user->isDeveloper()
            ? (int) ($data['institution_id'] ?? 0)
            : (int) $user->institution_id;
        $administratorId = $user->isAdmin()
            ? (int) $user->id
            : ($user->isDeveloper() ? (int) ($data['administrator_id'] ?? 0) : null);
        $dataEntryId = $user->isDataEntry() ? (int) $user->id : null;

        $exists = Orphan::query()
            ->where('institution_id', $institutionId)
            ->where('head_national_id', $data['head_national_id'])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'members.0.national_id' => __('ui.duplicate_head_national_id_in_institution'),
            ]);
        }

        $this->institutionStorageService->ensureWithinQuota(
            $institutionId,
            $this->calculateUploadedFilesSize([
                $request->file('orphan_photo'),
                $request->file('death_certificate'),
                $request->file('supporting_documents', []),
            ])
        );

        $orphanPhotoPath = $request->file('orphan_photo')?->store('orphan_attachments', 'local');
        $deathCertificatePath = $request->file('death_certificate')?->store('orphan_attachments', 'local');
        $supportingDocuments = [];
        foreach ($request->file('supporting_documents', []) as $file) {
            if (! $file) {
                continue;
            }

            $supportingDocuments[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store('orphan_attachments', 'local'),
                'mime_type' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
            ];
        }

        Orphan::query()->create([
            'institution_id' => $institutionId > 0 ? $institutionId : null,
            'administrator_id' => ($administratorId ?? 0) > 0 ? $administratorId : null,
            'data_entry_id' => $dataEntryId,
            'head_name' => $data['head_name'],
            'head_national_id' => $data['head_national_id'],
            'head_gender' => $data['head_gender'] ?? null,
            'head_birth_date' => $data['head_birth_date'] ?? null,
            'marital_status' => ($data['head_gender'] ?? null) === 'female'
                ? $this->orphanStatuses()[1]
                : $this->orphanStatuses()[0],
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_national_id' => $data['guardian_national_id'] ?? null,
            'guardian_mobile' => $data['guardian_mobile'] ?? null,
            'guardian_relation' => $data['guardian_relation'] ?? null,
            'siblings_count' => $data['siblings_count'] ?? null,
            'father_death_date' => $data['father_death_date'] ?? null,
            'mother_death_date' => $data['mother_death_date'] ?? null,
            'education_status' => $data['education_status'] ?? null,
            'detailed_address' => $data['detailed_address'] ?? null,
            'contact_phones' => $data['contact_phones'] ?? null,
            'governorate' => $data['governorate'] ?? null,
            'area' => $data['area'] ?? null,
            'family_members_count' => $familyMembersCount,
            'mobile' => $data['mobile'] ?? null,
            'chronic_diseases' => collect($data['members'][0]['chronic_disease_items'] ?? [])
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->unique()
                ->implode(', '),
            'disability' => collect($data['members'][0]['disability_items'] ?? [])
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->unique()
                ->implode(', '),
            'notes' => $data['notes'] ?? null,
            'head_age' => $data['head_age'] ?? null,
            'orphan_photo_path' => $orphanPhotoPath,
            'death_certificate_path' => $deathCertificatePath,
            'supporting_documents' => $supportingDocuments,
            'members_payload' => $data['members'] ?? [],
        ]);

        ActivityLogger::log($request, 'orphan.created');

        return redirect()->route('orphans.index')->with('status', __('ui.family_saved_success'));
    }

    public function editOrphan(Request $request, Orphan $orphan): View
    {
        $user = $request->user();
        if (
            ! $user->isDeveloper()
            && (
                (int) $user->institution_id !== (int) $orphan->institution_id
                || (
                    $user->isDataEntry()
                    && ! $user->hasPermission('view_all_families')
                    && (int) $orphan->data_entry_id !== (int) $user->id
                )
            )
        ) {
            abort(403);
        }

        $institutions = $user->isDeveloper() ? Institution::query()->orderBy('name')->get() : collect();
        $administrators = $user->isDeveloper()
            ? User::query()->where('role', User::ROLE_ADMIN)->with('institution')->orderBy('name')->get()
            : collect();
        $benefits = Benefit::query()->orderBy('name')->get();
        $governorateOptions = config('family_options.palestine_governorates', []);
        $chronicOptions = config('family_options.chronic_diseases', []);
        $disabilityOptions = config('family_options.disabilities', []);

        return view('families.create_orphan', compact(
            'user',
            'orphan',
            'institutions',
            'administrators',
            'benefits',
            'governorateOptions',
            'chronicOptions',
            'disabilityOptions'
        ));
    }

    public function updateOrphan(Request $request, Orphan $orphan): RedirectResponse
    {
        $user = $request->user();
        if (
            ! $user->isDeveloper()
            && (
                (int) $user->institution_id !== (int) $orphan->institution_id
                || (
                    $user->isDataEntry()
                    && ! $user->hasPermission('view_all_families')
                    && (int) $orphan->data_entry_id !== (int) $user->id
                )
            )
        ) {
            abort(403);
        }
        abort_unless($user->hasPermission('edit_families'), 403);

        $rules = [
            'head_gender' => ['required', Rule::in(['male', 'female'])],
            'governorate' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_national_id' => ['required', 'regex:/^\d{9}$/'],
            'guardian_mobile' => ['required', 'regex:/^\d{9}$/'],
            'guardian_relation' => ['required', Rule::in(['mother', 'grandfather', 'grandmother', 'uncle', 'maternal_uncle', 'brother', 'sister', 'other'])],
            'siblings_count' => ['required', 'integer', 'min:0', 'max:50'],
            'father_death_date' => ['required', 'date'],
            'mother_death_date' => ['nullable', 'date'],
            'education_status' => ['required', 'string', 'max:255'],
            'detailed_address' => ['nullable', 'string'],
            'contact_phones' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'orphan_photo' => ['nullable', 'file', 'image', 'max:10240'],
            'death_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'supporting_documents' => ['nullable', 'array'],
            'supporting_documents.*' => ['file', 'max:10240'],
            'members' => ['required', 'array', 'min:1'],
            'members.0.relation' => ['nullable', 'string', 'max:50'],
            'members.0.name' => ['required', 'string', 'max:255'],
            'members.0.national_id' => ['required', 'regex:/^\d{9}$/'],
            'members.0.birth_date' => ['required', 'date'],
            'members.0.birth_place' => ['required', 'string', 'max:255'],
            'members.0.health_status' => ['required', 'string', 'max:255'],
            'members.0.mobile' => ['nullable', 'regex:/^\d{9}$/'],
            'members.0.notes' => ['nullable', 'string'],
            'members.0.chronic_disease_items' => ['nullable', 'array'],
            'members.0.chronic_disease_items.*' => ['string', Rule::in(config('family_options.chronic_diseases', []))],
            'members.0.disability_items' => ['nullable', 'array'],
            'members.0.disability_items.*' => ['string', Rule::in(config('family_options.disabilities', []))],
            'members.0.attachments' => ['nullable', 'array'],
            'members.0.attachments.*' => ['file', 'max:10240'],
        ];

        if ($user->isDeveloper()) {
            $rules['institution_id'] = ['required', 'exists:institutions,id'];
            $rules['administrator_id'] = ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_ADMIN)];
        }

        $data = $request->validate($rules);
        $data['head_name'] = $data['members'][0]['name'];
        $data['head_national_id'] = $this->normalizeNationalId((string) $data['members'][0]['national_id']);
        $data['head_birth_date'] = $data['members'][0]['birth_date'];
        $data['head_age'] = $this->calculateAge($data['members'][0]['birth_date'] ?? null);
        $data['mobile'] = $data['members'][0]['mobile'] ?? null;
        if (! empty($data['guardian_national_id'])) {
            $data['guardian_national_id'] = $this->normalizeNationalId((string) $data['guardian_national_id']);
        }
        $familyMembersCount = max(
            1,
            1 + (int) ($data['siblings_count'] ?? 0) + (($data['guardian_relation'] ?? '') === 'mother' ? 1 : 0)
        );

        $institutionId = $user->isDeveloper()
            ? (int) ($data['institution_id'] ?? 0)
            : (int) $user->institution_id;
        $administratorId = $user->isAdmin()
            ? (int) $user->id
            : ($user->isDeveloper() ? (int) ($data['administrator_id'] ?? 0) : (int) ($orphan->administrator_id ?? 0));
        $dataEntryId = (int) ($orphan->data_entry_id ?? ($user->isDataEntry() ? $user->id : 0));

        $exists = Orphan::query()
            ->where('institution_id', $institutionId)
            ->where('head_national_id', $data['head_national_id'])
            ->where('id', '!=', $orphan->id)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'members.0.national_id' => __('ui.duplicate_head_national_id_in_institution'),
            ]);
        }

        $additionalBytes = $this->calculateUploadedFilesSize([
            $request->file('orphan_photo'),
            $request->file('death_certificate'),
            $request->file('supporting_documents', []),
        ]);
        $reclaimedBytes = 0;
        if ($request->hasFile('orphan_photo')) {
            $reclaimedBytes += $this->storedFileSize((string) $orphan->orphan_photo_path);
        }
        if ($request->hasFile('death_certificate')) {
            $reclaimedBytes += $this->storedFileSize((string) $orphan->death_certificate_path);
        }
        if ($request->hasFile('supporting_documents')) {
            $reclaimedBytes += $this->existingSupportingDocumentsSize($orphan);
        }

        $this->institutionStorageService->ensureWithinQuota(
            $institutionId,
            $additionalBytes,
            $reclaimedBytes
        );

        $orphanPhotoPath = $orphan->orphan_photo_path;
        if ($request->hasFile('orphan_photo')) {
            if (! empty($orphanPhotoPath)) {
                Storage::disk('local')->delete((string) $orphanPhotoPath);
            }
            $orphanPhotoPath = $request->file('orphan_photo')?->store('orphan_attachments', 'local');
        }

        $deathCertificatePath = $orphan->death_certificate_path;
        if ($request->hasFile('death_certificate')) {
            if (! empty($deathCertificatePath)) {
                Storage::disk('local')->delete((string) $deathCertificatePath);
            }
            $deathCertificatePath = $request->file('death_certificate')?->store('orphan_attachments', 'local');
        }

        $supportingDocuments = $orphan->supporting_documents ?? [];
        if ($request->hasFile('supporting_documents')) {
            foreach ($supportingDocuments as $doc) {
                $path = (string) ($doc['path'] ?? '');
                if ($path !== '') {
                    Storage::disk('local')->delete($path);
                }
            }

            $supportingDocuments = [];
            foreach ($request->file('supporting_documents', []) as $file) {
                if (! $file) {
                    continue;
                }

                $supportingDocuments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $file->store('orphan_attachments', 'local'),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => (int) $file->getSize(),
                ];
            }
        }

        $orphan->update([
            'institution_id' => $institutionId > 0 ? $institutionId : null,
            'administrator_id' => $administratorId > 0 ? $administratorId : null,
            'data_entry_id' => $dataEntryId > 0 ? $dataEntryId : null,
            'head_name' => $data['head_name'],
            'head_national_id' => $data['head_national_id'],
            'head_gender' => $data['head_gender'] ?? null,
            'head_birth_date' => $data['head_birth_date'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_national_id' => $data['guardian_national_id'] ?? null,
            'guardian_mobile' => $data['guardian_mobile'] ?? null,
            'guardian_relation' => $data['guardian_relation'] ?? null,
            'siblings_count' => $data['siblings_count'] ?? null,
            'father_death_date' => $data['father_death_date'] ?? null,
            'mother_death_date' => $data['mother_death_date'] ?? null,
            'education_status' => $data['education_status'] ?? null,
            'detailed_address' => $data['detailed_address'] ?? null,
            'contact_phones' => $data['contact_phones'] ?? null,
            'governorate' => $data['governorate'] ?? null,
            'area' => $data['area'] ?? null,
            'family_members_count' => $familyMembersCount,
            'mobile' => $data['mobile'] ?? null,
            'chronic_diseases' => collect($data['members'][0]['chronic_disease_items'] ?? [])
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->unique()
                ->implode(', '),
            'disability' => collect($data['members'][0]['disability_items'] ?? [])
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->unique()
                ->implode(', '),
            'notes' => $data['notes'] ?? null,
            'head_age' => $data['head_age'] ?? null,
            'orphan_photo_path' => $orphanPhotoPath,
            'death_certificate_path' => $deathCertificatePath,
            'supporting_documents' => $supportingDocuments,
            'members_payload' => $data['members'] ?? [],
        ]);

        ActivityLogger::log($request, 'orphan.updated', $orphan);

        return redirect()->route('orphans.index')->with('status', __('ui.family_updated_success'));
    }

    public function destroyOrphan(Request $request, Orphan $orphan): RedirectResponse
    {
        $user = $request->user();
        if (
            ! $user->isDeveloper()
            && (
                (int) $user->institution_id !== (int) $orphan->institution_id
                || (
                    $user->isDataEntry()
                    && ! $user->hasPermission('view_all_families')
                    && (int) $orphan->data_entry_id !== (int) $user->id
                )
            )
        ) {
            abort(403);
        }
        abort_unless($user->hasPermission('delete_families'), 403);

        foreach ([$orphan->orphan_photo_path, $orphan->death_certificate_path] as $path) {
            if (! empty($path)) {
                Storage::disk('local')->delete((string) $path);
            }
        }
        foreach (($orphan->supporting_documents ?? []) as $doc) {
            $path = (string) ($doc['path'] ?? '');
            if ($path !== '') {
                Storage::disk('local')->delete($path);
            }
        }

        $orphan->delete();
        ActivityLogger::log($request, 'orphan.deleted');

        return redirect()->route('orphans.index')->with('status', __('ui.family_deleted_success'));
    }

    public function membersSearch(Request $request): View
    {
        $user = $request->user();
        $query = FamilyMember::query()
            ->with(['family.institution', 'family.administrator', 'family.dataEntry'])
            ->whereIn('family_id', $this->scopeNonOrphanFamilies($user)->select('id'));

        if ($request->filled('member_q')) {
            $memberQ = trim((string) $request->string('member_q'));
            $query->where(function ($sub) use ($memberQ): void {
                $sub->where('name', 'like', "%{$memberQ}%")
                    ->orWhere('national_id', 'like', "%{$memberQ}%")
                    ->orWhere('mobile', 'like', "%{$memberQ}%");
            });
        }

        if ($request->filled('member_age_from')) {
            $ageFrom = max(0, (int) $request->input('member_age_from'));
            $query->whereNotNull('member_age')->where('member_age', '>=', $ageFrom);
        }

        if ($request->filled('member_age_to')) {
            $ageTo = max(0, (int) $request->input('member_age_to'));
            $query->whereNotNull('member_age')->where('member_age', '<=', $ageTo);
        }

        $members = $query->orderByDesc('id')->paginate(20);
        $members->appends($request->query());

        return view('families.members_search', compact('members'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->boolean('save_benefit')) {
            return $this->saveBenefitOnly($request);
        }

        $user = $request->user();
        $data = $this->validateFamily($request, $user);
        $meta = $this->resolveOwnerMeta($user, $data);
        $this->ensureNoDuplicateFamily(
            (int) ($meta['institution_id'] ?? 0),
            (string) $data['head_national_id']
        );
        $this->ensureNoDuplicateMembers(
            $data['members'] ?? [],
            (int) ($meta['institution_id'] ?? 0)
        );
        $this->ensureNoCrossDuplicateBetweenHeadAndMembers(
            (int) ($meta['institution_id'] ?? 0),
            (string) $data['head_national_id'],
            $data['members'] ?? []
        );
        $this->institutionStorageService->ensureWithinQuota(
            (int) ($meta['institution_id'] ?? 0),
            $this->calculateUploadedFilesSize([
                $request->file('family_attachments', []),
                $request->file('members', []),
            ])
        );

        $family = Family::create(array_merge($this->baseFamilyPayload($data), $meta));
        $memberIndexMap = $this->syncMembers($family, $data['members'] ?? []);
        $this->storeInlineAttachments($request, $family, $memberIndexMap);
        $this->syncBenefits($family, $data);
        ActivityLogger::log($request, 'family.created', $family);
        $this->notifyAboutFamilyCreation($request->user(), $family);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.family_created_success'),
                'family_id' => $family->id,
            ], 201);
        }

        return redirect()->route('families.index')->with('status', __('ui.family_saved_success'));
    }

    private function saveBenefitOnly(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'new_benefits' => ['nullable', 'array'],
            'new_benefits.*' => ['nullable', 'string', 'max:255'],
        ]);

        $names = collect($data['new_benefits'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('ui.enter_new_benefit_first'),
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['new_benefits' => __('ui.enter_new_benefit_first')]);
        }

        foreach ($names as $name) {
            Benefit::query()->firstOrCreate(['name' => $name]);
        }

        ActivityLogger::log($request, 'benefit.created.inline', null, [
            'count' => $names->count(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.benefit_saved_success'),
                'count' => $names->count(),
            ]);
        }

        return back()->withInput()->with('status', __('ui.benefit_saved_success'));
    }

    public function edit(Request $request, Family $family): View
    {
        $this->authorizeAccess($request->user(), $family);

        $user = $request->user();
        $institutions = $user->isDeveloper() ? Institution::orderBy('name')->get() : collect();
        $administrators = $user->isDeveloper()
            ? User::where('role', User::ROLE_ADMIN)->with('institution')->orderBy('name')->get()
            : collect();
        $benefits = Benefit::query()->orderBy('name')->get();
        $hasFamilyAttachmentsTable = Schema::hasTable('family_attachments');

        $with = ['members.benefits', 'benefits'];
        if ($hasFamilyAttachmentsTable) {
            $with[] = 'attachments.uploader';
            $with[] = 'attachments.member';
        }
        $family->load($with);
        $timeline = ActivityLog::query()
            ->with('user:id,name,email')
            ->where('subject_type', Family::class)
            ->where('subject_id', $family->id)
            ->latest()
            ->limit(20)
            ->get();

        return view('families.edit', compact(
            'family',
            'user',
            'institutions',
            'administrators',
            'benefits',
            'timeline',
            'hasFamilyAttachmentsTable'
        ));
    }

    public function update(Request $request, Family $family): RedirectResponse
    {
        $this->authorizeAccess($request->user(), $family);
        abort_unless($request->user()->hasPermission('edit_families'), 403);

        $user = $request->user();
        $data = $this->validateFamily($request, $user);
        $meta = $this->resolveOwnerMeta($user, $data, $family);
        $this->ensureNoDuplicateFamily(
            (int) ($meta['institution_id'] ?? 0),
            (string) $data['head_national_id'],
            $family->id
        );
        $this->ensureNoDuplicateMembers(
            $data['members'] ?? [],
            (int) ($meta['institution_id'] ?? 0),
            $family->id
        );
        $this->ensureNoCrossDuplicateBetweenHeadAndMembers(
            (int) ($meta['institution_id'] ?? 0),
            (string) $data['head_national_id'],
            $data['members'] ?? [],
            $family->id
        );
        $this->institutionStorageService->ensureWithinQuota(
            (int) ($meta['institution_id'] ?? 0),
            $this->calculateUploadedFilesSize([
                $request->file('family_attachments', []),
                $request->file('members', []),
            ])
        );

        $family->update(array_merge($this->baseFamilyPayload($data), $meta));
        $memberIndexMap = $this->syncMembers($family, $data['members'] ?? []);
        $this->storeInlineAttachments($request, $family, $memberIndexMap);
        $this->syncBenefits($family, $data);
        ActivityLogger::log($request, 'family.updated', $family);

        return redirect()->route('families.index')->with('status', __('ui.family_updated_success'));
    }

    public function destroy(Request $request, Family $family): RedirectResponse
    {
        $this->authorizeAccess($request->user(), $family);
        abort_unless($request->user()->hasPermission('delete_families'), 403);

        $familyId = (int) $family->id;

        try {
            DB::transaction(function () use ($request, $family, $familyId): void {
                ActivityLogger::log($request, 'family.deleted', $family);

                // Explicit cleanup to avoid any orphaned related rows.
                $family->benefits()->detach();
                $family->members()->delete();
                $family->tasks()->delete();
                $family->attachments()->delete();
                $family->delete();

                if (Family::query()->whereKey($familyId)->exists()) {
                    throw new \RuntimeException('Family record still exists after delete.');
                }
            });
        } catch (Throwable) {
            return redirect()
                ->route('families.index')
                ->withErrors(['delete' => __('ui.family_delete_failed')]);
        }

        return redirect()->route('families.index')->with('status', __('ui.family_deleted_success'));
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('export_reports'), 403);

        $query = $this->scopeFamilies($user);
        $this->applyFilters($query, $request);

        $families = $query
            ->with(['institution', 'administrator', 'dataEntry', 'members'])
            ->whereNotIn('marital_status', $this->orphanStatuses())
            ->orderByDesc('id')
            ->get();

        ActivityLogger::log($request, 'families.export_filtered', null, [
            'count' => $families->count(),
        ]);

        $grouped = $families->groupBy(fn (Family $family) => (int) ($family->institution_id ?? 0));
        $zipPath = tempnam(sys_get_temp_dir(), 'inst_families_export_');
        $zip = new ZipArchive();
        if ($zipPath === false || $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create export archive.');
        }

        foreach ($grouped as $institutionId => $institutionFamilies) {
            $institutionName = $institutionFamilies->first()?->institution?->name ?? __('ui.not_specified');
            $safeInstitutionName = $this->sanitizeFilename($institutionName);
            $xml = $this->buildInstitutionWorkbookXml($institutionFamilies);
            $zip->addFromString($safeInstitutionName.'.xls', $xml);
        }

        $zip->close();

        return response()->streamDownload(function () use ($zipPath): void {
            readfile($zipPath);
            @unlink($zipPath);
        }, 'institutions_families_export_'.now()->format('Ymd_His').'.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function exportSpecial(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('export_reports'), 403);

        $query = $this->scopeFamilies($user);
        $this->applyFilters($query, $request);

        $families = $query
            ->with(['institution', 'administrator', 'dataEntry', 'benefits', 'members.benefits'])
            ->whereNotIn('marital_status', $this->orphanStatuses())
            ->orderByDesc('id')
            ->get();

        ActivityLogger::log($request, 'families.export_special', null, [
            'count' => $families->count(),
        ]);

        return response()->streamDownload(function () use ($families): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                __('ui.csv_head_name'),
                __('ui.csv_head_national_id'),
                __('ui.csv_institution'),
                __('ui.csv_administrator'),
                __('ui.csv_data_entry'),
                __('ui.csv_mobile'),
                __('ui.csv_governorate'),
                __('ui.csv_area'),
                __('ui.csv_family_benefits'),
                __('ui.csv_member_name'),
                __('ui.csv_relation'),
                __('ui.csv_member_national_id'),
                __('ui.csv_member_mobile'),
                __('ui.csv_member_benefits'),
            ]);

            foreach ($families as $family) {
                $familyBenefits = $family->benefits->pluck('name')->implode(', ');

                if ($family->members->isEmpty()) {
                    fputcsv($handle, [
                        $family->head_name,
                        $family->head_national_id,
                        $family->institution?->name,
                        $family->administrator?->name,
                        $family->dataEntry?->name,
                        $family->mobile,
                        $family->governorate,
                        $family->area,
                        $familyBenefits,
                        '',
                        '',
                        '',
                        '',
                        '',
                    ]);

                    continue;
                }

                foreach ($family->members as $member) {
                    fputcsv($handle, [
                        $family->head_name,
                        $family->head_national_id,
                        $family->institution?->name,
                        $family->administrator?->name,
                        $family->dataEntry?->name,
                        $family->mobile,
                        $family->governorate,
                        $family->area,
                        $familyBenefits,
                        $member->name,
                        $member->relation,
                        $member->national_id,
                        $member->mobile,
                        $member->benefits->pluck('name')->implode(', '),
                    ]);
                }
            }

            fclose($handle);
        }, 'families_special_report_'.now()->format('Ymd_His').'.csv');
    }

    private function scopeFamilies(User $user)
    {
        $query = Family::query();

        if ($user->isDeveloper()) {
            return $query;
        }

        if ($user->isAdmin()) {
            return $query->where('institution_id', $user->institution_id);
        }

        $query->where('institution_id', $user->institution_id);

        if (! $user->hasPermission('view_all_families')) {
            $query->where('data_entry_id', $user->id);
        }

        return $query;
    }

    private function scopeOrphans(User $user)
    {
        $query = Orphan::query();

        if ($user->isDeveloper()) {
            return $query;
        }

        $query->where('institution_id', $user->institution_id);

        if ($user->isDataEntry() && ! $user->hasPermission('view_all_families')) {
            $query->where('data_entry_id', $user->id);
        }

        return $query;
    }

    private function scopeNonOrphanFamilies(User $user)
    {
        return $this->scopeFamilies($user)
            ->whereNotIn('marital_status', $this->orphanStatuses());
    }

    /**
     * @return array<int, string>
     */
    private function orphanStatuses(): array
    {
        return ["\u{064A}\u{062A}\u{064A}\u{0645}", "\u{064A}\u{062A}\u{064A}\u{0645}\u{0629}"];
    }

    private function marriedStatuses(): array
    {
        return ["\u{0645}\u{062A}\u{0632}\u{0648}\u{062C}", "\u{0645}\u{062A}\u{0632}\u{0648}\u{062C}\u{0629}"];
    }

    private function validateFamily(Request $request, User $user): array
    {
        $rules = [
            'head_name' => ['required', 'string', 'max:255'],
            'head_national_id' => ['required', 'regex:/^\\d{9}$/'],
            'head_gender' => ['required', Rule::in(['male', 'female'])],
            'head_birth_date' => ['required', 'date'],
            'marital_status' => ['required', 'string', 'max:255'],
            'spouse_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => in_array((string) $request->input('marital_status'), $this->marriedStatuses(), true)),
            ],
            'spouse_national_id' => [
                'nullable',
                'regex:/^\\d{9}$/',
                Rule::requiredIf(fn () => in_array((string) $request->input('marital_status'), $this->marriedStatuses(), true)),
            ],
            'spouse_birth_date' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => in_array((string) $request->input('marital_status'), $this->marriedStatuses(), true)),
            ],
            'spouse_pregnant' => [
                'nullable',
                'boolean',
                Rule::requiredIf(fn () => in_array((string) $request->input('marital_status'), $this->marriedStatuses(), true)),
            ],
            'governorate' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'family_members_count' => ['nullable', 'integer', 'min:1'],
            'mobile' => ['nullable', 'regex:/^\d{9}$/'],
            'war_injury' => ['nullable', 'boolean'],
            'chronic_disease_items' => ['nullable', 'array'],
            'chronic_disease_items.*' => ['string', Rule::in(config('family_options.chronic_diseases', []))],
            'chronic_diseases_extra' => ['nullable', 'string', 'max:255'],
            'disability_items' => ['nullable', 'array'],
            'disability_items.*' => ['string', Rule::in(config('family_options.disabilities', []))],
            'disability_extra' => ['nullable', 'string', 'max:255'],
            'chronic_diseases' => ['nullable', 'string'],
            'disability' => ['nullable', 'string'],
            'employment_status' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'head_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'spouse_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'benefits_from_institution' => ['nullable', 'string'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_national_id' => ['nullable', 'regex:/^\\d{9}$/'],
            'mother_marital_status' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_national_id' => ['nullable', 'regex:/^\\d{9}$/'],
            'guardian_mobile' => ['nullable', 'regex:/^\\d{9}$/'],
            'siblings_count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'father_death_date' => ['nullable', 'date'],
            'mother_death_date' => ['nullable', 'date'],
            'education_status' => ['nullable', 'string', 'max:255'],
            'detailed_address' => ['nullable', 'string'],
            'contact_phones' => ['nullable', 'string'],
            'benefit_ids' => ['nullable', 'array'],
            'benefit_ids.*' => ['integer', 'exists:benefits,id'],
            'new_benefits' => ['nullable', 'array'],
            'new_benefits.*' => ['nullable', 'string', 'max:255'],
            'members' => ['nullable', 'array'],
            'members.*.relation' => ['nullable', 'string', 'max:50'],
            'members.*.name' => ['nullable', 'string', 'max:255'],
            'members.*.national_id' => ['nullable', 'string', 'max:30'],
            'members.*.birth_date' => ['nullable', 'date'],
            'members.*.birth_place' => ['nullable', 'string', 'max:255'],
            'members.*.mobile' => ['nullable', 'regex:/^\d{9}$/'],
            'members.*.head_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'members.*.member_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'members.*.health_status' => ['nullable', 'string', 'max:255'],
            'members.*.chronic_disease_items' => ['nullable', 'array'],
            'members.*.chronic_disease_items.*' => ['string', Rule::in(config('family_options.chronic_diseases', []))],
            'members.*.disability_items' => ['nullable', 'array'],
            'members.*.disability_items.*' => ['string', Rule::in(config('family_options.disabilities', []))],
            'members.*.notes' => ['nullable', 'string'],
            'members.*.benefit_ids' => ['nullable', 'array'],
            'members.*.benefit_ids.*' => ['integer', 'exists:benefits,id'],
            'family_attachments' => ['nullable', 'array'],
            'family_attachments.*' => ['file', 'max:10240'],
            'members.*.attachments' => ['nullable', 'array'],
            'members.*.attachments.*' => ['file', 'max:10240'],
        ];

        if ($user->isDeveloper()) {
            $rules['institution_id'] = ['required', 'exists:institutions,id'];
            $rules['administrator_id'] = ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_ADMIN)];
        }

        return $request->validate($rules);
    }

    private function resolveOwnerMeta(User $user, array $data, ?Family $family = null): array
    {
        if ($user->isDeveloper()) {
            if (! empty($data['administrator_id'])) {
                $administrator = User::query()
                    ->where('role', User::ROLE_ADMIN)
                    ->findOrFail($data['administrator_id']);

                if ((int) $administrator->institution_id !== (int) $data['institution_id']) {
                    throw ValidationException::withMessages([
                        'administrator_id' => __('ui.admin_not_in_selected_institution'),
                    ]);
                }
            }

            return [
                'institution_id' => $data['institution_id'],
                'administrator_id' => $data['administrator_id'] ?? $family?->administrator_id,
                'data_entry_id' => $family?->data_entry_id,
            ];
        }

        if ($user->isAdmin()) {
            return [
                'institution_id' => $user->institution_id,
                'administrator_id' => $user->id,
                'data_entry_id' => $family?->data_entry_id,
            ];
        }

        return [
            'institution_id' => $user->institution_id,
            'administrator_id' => $user->created_by,
            'data_entry_id' => $user->id,
        ];
    }

    private function baseFamilyPayload(array $data): array
    {
        $headAge = $this->calculateAge($data['head_birth_date'] ?? null);
        $spouseAge = $this->calculateAge($data['spouse_birth_date'] ?? null);
        $membersCount = collect($data['members'] ?? [])
            ->filter(fn ($member) => ! empty($member['name']))
            ->count();
        $hasSpouseName = trim((string) ($data['spouse_name'] ?? '')) !== '';
        $familyMembersCount = 1 + $membersCount + ($hasSpouseName ? 1 : 0);
        $chronicDiseases = $this->mergeListWithExtra(
            $data['chronic_disease_items'] ?? [],
            $data['chronic_diseases_extra'] ?? null,
            $data['chronic_diseases'] ?? null
        );
        $disability = $this->mergeListWithExtra(
            $data['disability_items'] ?? [],
            $data['disability_extra'] ?? null,
            $data['disability'] ?? null
        );

        return [
            'spouse_pregnant' => in_array((string) ($data['marital_status'] ?? ''), $this->marriedStatuses(), true)
                ? (bool) ($data['spouse_pregnant'] ?? false)
                : null,
            'head_name' => $data['head_name'],
            'head_national_id' => $this->normalizeNationalId((string) $data['head_national_id']),
            'head_gender' => $data['head_gender'] ?? null,
            'head_birth_date' => $data['head_birth_date'] ?? null,
            'marital_status' => $data['marital_status'] ?? null,
            'spouse_name' => $data['spouse_name'] ?? null,
            'spouse_national_id' => $data['spouse_national_id'] ?? null,
            'spouse_birth_date' => $data['spouse_birth_date'] ?? null,
            'mother_name' => $data['mother_name'] ?? null,
            'mother_national_id' => ! empty($data['mother_national_id'])
                ? $this->normalizeNationalId((string) $data['mother_national_id'])
                : null,
            'mother_marital_status' => $data['mother_marital_status'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_national_id' => ! empty($data['guardian_national_id'])
                ? $this->normalizeNationalId((string) $data['guardian_national_id'])
                : null,
            'guardian_mobile' => $data['guardian_mobile'] ?? null,
            'siblings_count' => $data['siblings_count'] ?? null,
            'father_death_date' => $data['father_death_date'] ?? null,
            'mother_death_date' => $data['mother_death_date'] ?? null,
            'education_status' => $data['education_status'] ?? null,
            'detailed_address' => $data['detailed_address'] ?? null,
            'contact_phones' => $data['contact_phones'] ?? null,
            'governorate' => $data['governorate'] ?? null,
            'area' => $data['area'] ?? null,
            'family_members_count' => $familyMembersCount,
            'mobile' => $data['mobile'] ?? null,
            'war_injury' => (bool) ($data['war_injury'] ?? false),
            'chronic_diseases' => $chronicDiseases,
            'disability' => $disability,
            'employment_status' => $data['employment_status'] ?? null,
            'notes' => $data['notes'] ?? null,
            'head_age' => $headAge,
            'spouse_age' => $spouseAge,
            'benefits_from_institution' => $data['benefits_from_institution'] ?? null,
        ];
    }

    private function syncMembers(Family $family, array $members): array
    {
        $family->members()->delete();
        $createdByIndex = [];

        foreach ($members as $index => $member) {
            if (empty($member['name'])) {
                continue;
            }

            $familyMember = $family->members()->create([
                'relation' => $member['relation'] ?? null,
                'name' => $member['name'],
                'national_id' => ! empty($member['national_id'])
                    ? $this->normalizeNationalId((string) $member['national_id'])
                    : null,
                'birth_date' => $member['birth_date'] ?? null,
                'birth_place' => $member['birth_place'] ?? null,
                'mobile' => $member['mobile'] ?? $family->mobile,
                'head_age' => $family->head_age,
                'member_age' => $this->calculateAge($member['birth_date'] ?? null),
                'health_status' => $member['health_status'] ?? null,
                'chronic_diseases' => collect($member['chronic_disease_items'] ?? [])
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->unique()
                    ->implode(', '),
                'disability' => collect($member['disability_items'] ?? [])
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->unique()
                    ->implode(', '),
                'notes' => $member['notes'] ?? null,
            ]);

            $memberBenefitIds = collect($member['benefit_ids'] ?? [])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $familyMember->benefits()->sync($memberBenefitIds);
            $createdByIndex[(string) $index] = $familyMember;
        }

        return $createdByIndex;
    }

    private function storeInlineAttachments(Request $request, Family $family, array $memberIndexMap): void
    {
        if (! Schema::hasTable('family_attachments')) {
            return;
        }

        foreach ($request->file('family_attachments', []) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('family_attachments', 'local');
            $family->attachments()->create([
                'family_member_id' => null,
                'uploaded_by' => $request->user()?->id,
                'name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        }

        foreach ($memberIndexMap as $index => $member) {
            $memberFiles = $request->file("members.$index.attachments", []);
            foreach ($memberFiles as $file) {
                if (! $file) {
                    continue;
                }

                $path = $file->store('family_attachments', 'local');
                $family->attachments()->create([
                    'family_member_id' => $member->id,
                    'uploaded_by' => $request->user()?->id,
                    'name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => (int) $file->getSize(),
                ]);
            }
        }
    }

    private function storeOrphanDocuments(Request $request, Family $family, array $memberIndexMap): void
    {
        if (! Schema::hasTable('family_attachments')) {
            return;
        }

        $member = $memberIndexMap['0'] ?? null;
        $uploadedBy = $request->user()?->id;

        $saveAttachment = function ($file, string $namePrefix) use ($family, $member, $uploadedBy): void {
            if (! $file) {
                return;
            }

            $path = $file->store('family_attachments', 'local');
            $family->attachments()->create([
                'family_member_id' => $member?->id,
                'uploaded_by' => $uploadedBy,
                'name' => $namePrefix.' - '.$file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        };

        $saveAttachment($request->file('orphan_photo'), __('ui.orphan_photo_attachment_label'));
        $saveAttachment($request->file('death_certificate'), __('ui.death_certificate_attachment_label'));

        foreach ($request->file('supporting_documents', []) as $file) {
            $saveAttachment($file, __('ui.supporting_document_attachment_label'));
        }
    }

    private function syncBenefits(Family $family, array $data): void
    {
        $benefitIds = collect($data['benefit_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($data['new_benefits'] ?? [] as $newBenefitName) {
            $name = trim((string) $newBenefitName);
            if ($name === '') {
                continue;
            }

            $benefit = Benefit::firstOrCreate(['name' => $name]);
            $benefitIds->push($benefit->id);
        }

        $benefitIds = $benefitIds->unique()->values();
        $family->benefits()->sync($benefitIds);

        $names = Benefit::query()
            ->whereIn('id', $benefitIds)
            ->pluck('name')
            ->implode(', ');

        $family->update([
            'benefits_from_institution' => $names !== '' ? $names : ($data['benefits_from_institution'] ?? null),
        ]);
    }

    private function authorizeAccess(User $user, Family $family): void
    {
        abort_unless(FamilyAccess::canAccess($user, $family), 403);
    }

    private function calculateAge(?string $birthDate): ?int
    {
        if (empty($birthDate)) {
            return null;
        }

        try {
            return Carbon::parse($birthDate)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    private function mergeListWithExtra(array $selected, ?string $extra, ?string $fallback): ?string
    {
        $items = collect($selected)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();

        if (! empty($extra)) {
            $items->push(trim($extra));
        }

        $merged = $items->unique()->implode(', ');

        if ($merged !== '') {
            return $merged;
        }

        return ! empty($fallback) ? trim($fallback) : null;
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($sub) use ($q): void {
                $sub->where('head_name', 'like', "%{$q}%")
                    ->orWhere('head_national_id', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%");
            });
        }

        foreach (['marital_status', 'governorate', 'institution_id', 'administrator_id', 'data_entry_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('min_age')) {
            $query->where('head_age', '>=', (int) $request->input('min_age'));
        }

        if ($request->filled('max_age')) {
            $query->where('head_age', '<=', (int) $request->input('max_age'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        if ($request->filled('war_injury')) {
            $query->where('war_injury', (bool) $request->boolean('war_injury'));
        }

        if ($request->filled('has_chronic')) {
            if ($request->boolean('has_chronic')) {
                $query->whereNotNull('chronic_diseases')->where('chronic_diseases', '!=', '');
            } else {
                $query->where(function ($q): void {
                    $q->whereNull('chronic_diseases')->orWhere('chronic_diseases', '');
                });
            }
        }

        if ($request->filled('has_disability')) {
            if ($request->boolean('has_disability')) {
                $query->whereNotNull('disability')->where('disability', '!=', '');
            } else {
                $query->where(function ($q): void {
                    $q->whereNull('disability')->orWhere('disability', '');
                });
            }
        }

        if ($request->filled('benefit_id')) {
            $benefitFilter = (string) $request->input('benefit_id');

            if ($benefitFilter === 'none') {
                $query->whereDoesntHave('benefits')
                    ->where(function ($q): void {
                        $q->whereNull('benefits_from_institution')
                            ->orWhere('benefits_from_institution', '')
                            ->orWhereRaw('TRIM(benefits_from_institution) = ?', ['']);
                    });
            } else {
                $benefitId = (int) $benefitFilter;
                if ($benefitId > 0) {
                    $query->whereHas('benefits', function ($q) use ($benefitId): void {
                        $q->where('benefits.id', $benefitId);
                    });
                }
            }
        }

        if ($request->filled('stale_days')) {
            $days = max(1, (int) $request->input('stale_days'));
            $query->where('updated_at', '<=', now()->subDays($days));
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sort = (string) $request->input('sort', 'latest');
        if ($sort === 'priority') {
            $query
                ->orderByDesc('war_injury')
                ->orderByRaw("CASE WHEN disability IS NULL OR disability = '' THEN 0 ELSE 1 END DESC")
                ->orderByRaw("CASE WHEN chronic_diseases IS NULL OR chronic_diseases = '' THEN 0 ELSE 1 END DESC")
                ->orderByDesc('family_members_count')
                ->orderByDesc('updated_at');

            return;
        }

        $query->latest();
    }

    private function buildSmartAlerts($scopedQuery): array
    {
        $missingMobile = (clone $scopedQuery)
            ->where(function ($query): void {
                $query->whereNull('mobile')->orWhere('mobile', '');
            })
            ->count();

        $staleRecords = (clone $scopedQuery)
            ->where('updated_at', '<', now()->subDays(90))
            ->count();

        $duplicateNationalIds = (clone $scopedQuery)
            ->selectRaw('head_national_id, count(*) as total')
            ->whereNotNull('head_national_id')
            ->groupBy('head_national_id')
            ->havingRaw('count(*) > 1')
            ->count();

        return [
            'missing_mobile' => $missingMobile,
            'stale_records' => $staleRecords,
            'duplicate_national_ids' => $duplicateNationalIds,
        ];
    }

    private function notifyAboutFamilyCreation(User $actor, Family $family): void
    {
        $recipients = User::query()
            ->where('role', User::ROLE_DEVELOPER)
            ->orWhere(function ($q) use ($family) {
                $q->where('role', User::ROLE_ADMIN)
                    ->where('institution_id', $family->institution_id);
            })
            ->get()
            ->where('id', '!=', $actor->id);

        foreach ($recipients as $recipient) {
            $recipient->notify(new FamilyCreatedNotification($family));
        }
    }

    private function ensureNoDuplicateFamily(int $institutionId, string $headNationalId, ?int $ignoreFamilyId = null): void
    {
        $normalizedNationalId = $this->normalizeNationalId($headNationalId);
        if ($normalizedNationalId === '' || $institutionId <= 0) {
            return;
        }

        $query = Family::query()
            ->where('institution_id', $institutionId)
            ->where('head_national_id', $normalizedNationalId);

        if ($ignoreFamilyId !== null) {
            $query->where('id', '!=', $ignoreFamilyId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'head_national_id' => __('ui.duplicate_head_national_id_in_institution'),
            ]);
        }
    }

    private function ensureNoDuplicateMembers(array $members, int $institutionId, ?int $ignoreFamilyId = null): void
    {
        if ($institutionId <= 0) {
            return;
        }

        $memberNationalIds = collect($members)
            ->map(function ($member): ?string {
                $name = trim((string) ($member['name'] ?? ''));
                $nationalId = trim((string) ($member['national_id'] ?? ''));
                if ($name === '' || $nationalId === '') {
                    return null;
                }

                return $this->normalizeNationalId($nationalId);
            })
            ->filter()
            ->values();

        if ($memberNationalIds->isEmpty()) {
            return;
        }

        if ($memberNationalIds->count() !== $memberNationalIds->unique()->count()) {
            throw ValidationException::withMessages([
                'members' => __('ui.duplicate_member_in_same_family'),
            ]);
        }

        $existingQuery = FamilyMember::query()
            ->join('families', 'families.id', '=', 'family_members.family_id')
            ->where('families.institution_id', $institutionId)
            ->whereIn('family_members.national_id', $memberNationalIds->all());

        if ($ignoreFamilyId !== null) {
            $existingQuery->where('family_members.family_id', '!=', $ignoreFamilyId);
        }

        if ($existingQuery->exists()) {
            throw ValidationException::withMessages([
                'members' => __('ui.duplicate_member_already_exists'),
            ]);
        }
    }

    private function ensureNoCrossDuplicateBetweenHeadAndMembers(
        int $institutionId,
        string $headNationalId,
        array $members,
        ?int $ignoreFamilyId = null
    ): void {
        if ($institutionId <= 0) {
            return;
        }

        $normalizedHeadId = $this->normalizeNationalId($headNationalId);
        $memberNationalIds = collect($members)
            ->map(fn ($member) => $this->normalizeNationalId((string) ($member['national_id'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedHeadId !== '' && $memberNationalIds->contains($normalizedHeadId)) {
            throw ValidationException::withMessages([
                'members' => __('ui.duplicate_member_matches_family_head'),
            ]);
        }

        if ($normalizedHeadId !== '') {
            $headAsMemberQuery = FamilyMember::query()
                ->join('families', 'families.id', '=', 'family_members.family_id')
                ->where('families.institution_id', $institutionId)
                ->where('family_members.national_id', $normalizedHeadId);

            if ($ignoreFamilyId !== null) {
                $headAsMemberQuery->where('family_members.family_id', '!=', $ignoreFamilyId);
            }

            if ($headAsMemberQuery->exists()) {
                throw ValidationException::withMessages([
                    'head_national_id' => __('ui.duplicate_head_matches_existing_member'),
                ]);
            }
        }

        if ($memberNationalIds->isEmpty()) {
            return;
        }

        $memberAsHeadQuery = Family::query()
            ->where('institution_id', $institutionId)
            ->whereIn('head_national_id', $memberNationalIds->all());

        if ($ignoreFamilyId !== null) {
            $memberAsHeadQuery->where('id', '!=', $ignoreFamilyId);
        }

        if ($memberAsHeadQuery->exists()) {
            throw ValidationException::withMessages([
                'members' => __('ui.duplicate_member_already_exists'),
            ]);
        }
    }

    private function normalizeNationalId(string $value): string
    {
        $value = trim($value);
        $value = strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            'ط¸آ ' => '0', 'ط¸طŒ' => '1', 'ط¸آ¢' => '2', 'ط¸آ£' => '3', 'ط¸آ¤' => '4',
            'ط¸آ¥' => '5', 'ط¸آ¦' => '6', 'ط¸آ§' => '7', 'ط¸آ¨' => '8', 'ط¸آ©' => '9',
            'ط؛آ°' => '0', 'ط؛آ±' => '1', 'ط؛آ²' => '2', 'ط؛آ³' => '3', 'ط؛آ´' => '4',
            'ط؛آµ' => '5', 'ط؛آ¶' => '6', 'ط؛آ·' => '7', 'ط؛آ¸' => '8', 'ط؛آ¹' => '9',
        ]);
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        return $value;
    }

    private function buildExcelWorkbookXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"';
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">';
        $xml .= '<Styles><Style ss:ID="Header"><Font ss:Bold="1"/></Style></Styles>';

        foreach ($sheets as $sheet) {
            $sheetName = $this->excelXmlEscape((string) ($sheet['name'] ?? 'Sheet'));
            $headers = $sheet['headers'] ?? [];
            $rows = $sheet['rows'] ?? [];

            $xml .= '<Worksheet ss:Name="'.$sheetName.'"><Table>';
            $xml .= $this->buildExcelHeaderRow($headers);

            foreach ($rows as $row) {
                $xml .= $this->buildExcelDataRow($row);
            }

            $xml .= '</Table></Worksheet>';
        }

        $xml .= '</Workbook>';

        return $xml;
    }

    private function buildExcelHeaderRow(array $headers): string
    {
        $cells = '';
        foreach ($headers as $value) {
            $cells .= '<Cell ss:StyleID="Header"><Data ss:Type="String">'.$this->excelXmlEscape((string) $value).'</Data></Cell>';
        }

        return '<Row>'.$cells.'</Row>';
    }

    private function buildExcelDataRow(array $values): string
    {
        $cells = '';
        foreach ($values as $value) {
            $cells .= '<Cell><Data ss:Type="String">'.$this->excelXmlEscape((string) ($value ?? '')).'</Data></Cell>';
        }

        return '<Row>'.$cells.'</Row>';
    }

    private function excelXmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function buildInstitutionWorkbookXml($families): string
    {
        $familyHeaders = [
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
        ];

        $familyRows = [];
        $memberRows = [];
        foreach ($families as $family) {
            $familyRows[] = [
                $family->head_name,
                $family->head_national_id,
                optional($family->head_birth_date)->format('Y-m-d'),
                $family->marital_status,
                $family->spouse_name,
                $family->spouse_national_id,
                optional($family->spouse_birth_date)->format('Y-m-d'),
                $family->governorate,
                $family->area,
                (string) $family->family_members_count,
                $family->mobile,
                $family->war_injury ? __('ui.yes') : __('ui.no'),
                $family->chronic_diseases,
                $family->disability,
                $family->employment_status,
                $family->notes,
                (string) ($family->head_age ?? ''),
                (string) ($family->spouse_age ?? ''),
                $family->benefits_from_institution,
                $family->institution?->name,
                $family->administrator?->name,
                $family->dataEntry?->name,
                (string) $family->family_members_count,
            ];

            foreach ($family->members as $member) {
                $memberRows[] = [
                    $family->head_name,
                    $family->head_national_id,
                    $member->name,
                    $member->national_id,
                    optional($member->birth_date)->format('Y-m-d'),
                    $member->mobile,
                    (string) ($member->member_age ?? ''),
                ];
            }
        }

        return $this->buildExcelWorkbookXml([
            [
                'name' => __('ui.families'),
                'headers' => $familyHeaders,
                'rows' => $familyRows,
            ],
            [
                'name' => __('ui.family_members_sheet_name'),
                'headers' => [
                    __('ui.csv_head_name'),
                    __('ui.csv_head_national_id'),
                    __('ui.csv_member_name'),
                    __('ui.csv_member_national_id'),
                    __('ui.csv_member_birth_date'),
                    __('ui.csv_member_mobile'),
                    __('ui.csv_member_age'),
                ],
                'rows' => $memberRows,
            ],
        ]);
    }

    private function sanitizeFilename(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]+/u', '_', $value) ?? $value;
        $value = preg_replace('/\\s+/u', '_', $value) ?? $value;
        $value = trim($value, '._');

        return $value !== '' ? $value : 'institution';
    }

    private function calculateUploadedFilesSize(mixed $input): int
    {
        if ($input === null) {
            return 0;
        }

        if (is_array($input)) {
            $total = 0;
            foreach ($input as $item) {
                $total += $this->calculateUploadedFilesSize($item);
            }

            return $total;
        }

        if (is_object($input) && method_exists($input, 'getSize')) {
            return (int) ($input->getSize() ?? 0);
        }

        return 0;
    }

    private function storedFileSize(string $path): int
    {
        $path = trim($path);
        if ($path === '') {
            return 0;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return 0;
        }

        return (int) $disk->size($path);
    }

    private function existingSupportingDocumentsSize(Orphan $orphan): int
    {
        $total = 0;
        foreach ((array) ($orphan->supporting_documents ?? []) as $doc) {
            $size = (int) ($doc['size'] ?? 0);
            if ($size > 0) {
                $total += $size;
                continue;
            }

            $total += $this->storedFileSize((string) ($doc['path'] ?? ''));
        }

        return $total;
    }
}




