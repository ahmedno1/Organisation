<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'storage_quota_mb',
        'logo_path',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected $casts = [
        'storage_quota_mb' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        $resolvedPath = $this->resolveLogoStoragePath((string) $this->logo_path);
        if ($resolvedPath === null) {
            return null;
        }

        return route('institutions.logo', $this, false);
    }

    private function resolveLogoStoragePath(string $path): ?string
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
        $normalized = preg_replace('#^(storage|public)/#i', '', $normalized) ?? $normalized;
        $candidates = [$normalized];

        $logoSegment = 'institution-logos/';
        $segmentPos = stripos($normalized, $logoSegment);
        if ($segmentPos !== false) {
            $candidates[] = substr($normalized, $segmentPos);
        }

        foreach (array_unique($candidates) as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            if (Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
