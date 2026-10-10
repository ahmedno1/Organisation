<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyAttachment;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\FamilyAccess;
use App\Services\InstitutionStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FamilyAttachmentController extends Controller
{
    public function __construct(private readonly InstitutionStorageService $institutionStorageService)
    {
    }

    public function store(Request $request, Family $family): RedirectResponse
    {
        if (! Schema::hasTable('family_attachments')) {
            return back()->withErrors(['family_attachments' => __('ui.table_missing_run_migrate', ['table' => 'family_attachments'])]);
        }

        $this->authorizeFamily($request->user(), $family);

        $data = $request->validate([
            'attachment' => ['required', 'file', 'max:10240'],
            'name' => ['nullable', 'string', 'max:255'],
            'member_id' => ['nullable', 'integer'],
        ]);

        $member = null;
        $memberId = (int) ($data['member_id'] ?? 0);
        if ($memberId > 0) {
            $member = FamilyMember::query()
                ->where('family_id', $family->id)
                ->find($memberId);

            if (! $member) {
                return back()->withErrors(['member_id' => __('ui.member_not_in_family')]);
            }
        }

        $file = $data['attachment'];
        $this->institutionStorageService->ensureWithinQuota(
            (int) $family->institution_id,
            (int) $file->getSize()
        );
        $path = $file->store('family_attachments', 'local');

        $attachment = $family->attachments()->create([
            'family_member_id' => $member?->id,
            'uploaded_by' => $request->user()?->id,
            'name' => $data['name'] ?? $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
        ]);

        ActivityLogger::log($request, 'family.attachment_uploaded', $family, [
            'attachment_id' => $attachment->id,
            'family_member_id' => $attachment->family_member_id,
        ]);

        return back()->with('status', __('ui.attachment_uploaded'));
    }

    public function download(Request $request, Family $family, int $attachment): BinaryFileResponse
    {
        abort_unless(Schema::hasTable('family_attachments'), 404);
        $this->authorizeFamily($request->user(), $family);

        $attachment = FamilyAttachment::query()->findOrFail($attachment);
        abort_unless((int) $attachment->family_id === (int) $family->id, 404);

        ActivityLogger::log($request, 'family.attachment_downloaded', $family, ['attachment_id' => $attachment->id]);

        return Storage::disk('local')->download($attachment->file_path, $attachment->name);
    }

    public function destroy(Request $request, Family $family, int $attachment): RedirectResponse
    {
        if (! Schema::hasTable('family_attachments')) {
            return back()->withErrors(['family_attachments' => __('ui.table_missing', ['table' => 'family_attachments'])]);
        }

        $this->authorizeFamily($request->user(), $family);

        $attachment = FamilyAttachment::query()->findOrFail($attachment);
        abort_unless((int) $attachment->family_id === (int) $family->id, 404);

        Storage::disk('local')->delete($attachment->file_path);
        $attachmentId = $attachment->id;
        $attachment->delete();

        ActivityLogger::log($request, 'family.attachment_deleted', $family, ['attachment_id' => $attachmentId]);

        return back()->with('status', __('ui.attachment_deleted'));
    }

    private function authorizeFamily(User $user, Family $family): void
    {
        abort_unless(FamilyAccess::canAccess($user, $family), 403);
    }
}
