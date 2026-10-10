<?php

namespace App\Services;

use App\Models\FamilyAttachment;
use App\Models\Institution;
use App\Models\Orphan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InstitutionStorageService
{
    private const MB = 1048576;

    public function ensureWithinQuota(int $institutionId, int $additionalBytes, int $reclaimedBytes = 0): void
    {
        if ($institutionId <= 0 || $additionalBytes <= 0) {
            return;
        }

        $institution = Institution::query()->find($institutionId);
        if (! $institution) {
            return;
        }

        $quotaMb = $institution->storage_quota_mb;
        if ($quotaMb === null || (int) $quotaMb <= 0) {
            return;
        }

        $quotaBytes = (int) $quotaMb * self::MB;
        $usedBytes = $this->getUsedBytes($institution);
        $projectedBytes = max(0, $usedBytes - max(0, $reclaimedBytes)) + max(0, $additionalBytes);

        if ($projectedBytes <= $quotaBytes) {
            return;
        }

        $remainingBytes = max(0, $quotaBytes - max(0, $usedBytes - max(0, $reclaimedBytes)));

        throw ValidationException::withMessages([
            'storage_quota' => __('ui.institution_storage_quota_exceeded', [
                'quota_mb' => $this->mb($quotaBytes),
                'used_mb' => $this->mb($usedBytes),
                'requested_mb' => $this->mb($additionalBytes),
                'remaining_mb' => $this->mb($remainingBytes),
            ]),
        ]);
    }

    public function getUsedBytes(Institution $institution): int
    {
        $institutionId = (int) $institution->id;
        if ($institutionId <= 0) {
            return 0;
        }

        $attachmentsBytes = (int) FamilyAttachment::query()
            ->whereHas('family', function ($query) use ($institutionId): void {
                $query->where('institution_id', $institutionId);
            })
            ->sum('size');

        $orphansBytes = $this->getOrphansBytes($institutionId);

        return $attachmentsBytes + $orphansBytes;
    }

    private function getOrphansBytes(int $institutionId): int
    {
        $orphans = Orphan::query()
            ->where('institution_id', $institutionId)
            ->get(['orphan_photo_path', 'death_certificate_path', 'supporting_documents']);

        $total = 0;
        foreach ($orphans as $orphan) {
            $total += $this->pathSize((string) ($orphan->orphan_photo_path ?? ''));
            $total += $this->pathSize((string) ($orphan->death_certificate_path ?? ''));

            foreach ((array) ($orphan->supporting_documents ?? []) as $doc) {
                $size = (int) ($doc['size'] ?? 0);
                if ($size > 0) {
                    $total += $size;
                    continue;
                }

                $total += $this->pathSize((string) ($doc['path'] ?? ''));
            }
        }

        return $total;
    }

    private function pathSize(string $path): int
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

    private function mb(int $bytes): string
    {
        return number_format(max(0, $bytes) / self::MB, 2);
    }
}
