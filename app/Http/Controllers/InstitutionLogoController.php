<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InstitutionLogoController extends Controller
{
    public function show(Institution $institution): BinaryFileResponse
    {
        $path = $this->resolveLogoStoragePath((string) $institution->logo_path);
        if ($path === null) {
            abort(404);
        }
        abort_unless(Storage::disk('public')->exists($path), 404);

        $absolutePath = Storage::disk('public')->path($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
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
