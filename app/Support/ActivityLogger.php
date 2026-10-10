<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    public static function log(Request $request, string $action, ?Model $subject = null, array $meta = []): void
    {
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip' => $request->ip(),
            'user_agent' => self::truncate((string) $request->userAgent(), 1024),
            'meta' => self::sanitizeMeta($meta),
        ]);
    }

    private static function sanitizeMeta(array $meta): array
    {
        $sensitiveKeys = [
            'password',
            'token',
            'secret',
            'national_id',
            'mobile',
            'phone',
            'email',
        ];

        $sanitize = function ($value, ?string $key = null) use (&$sanitize, $sensitiveKeys) {
            if (is_array($value)) {
                $clean = [];
                foreach ($value as $nestedKey => $nestedValue) {
                    $nestedKeyString = is_string($nestedKey) ? $nestedKey : null;
                    $clean[$nestedKey] = $sanitize($nestedValue, $nestedKeyString);
                }

                return $clean;
            }

            if ($key !== null) {
                $lowerKey = strtolower($key);
                foreach ($sensitiveKeys as $sensitive) {
                    if (str_contains($lowerKey, $sensitive)) {
                        return '[REDACTED]';
                    }
                }
            }

            if (is_string($value)) {
                return self::truncate($value, 512);
            }

            return $value;
        };

        return $sanitize($meta);
    }

    private static function truncate(string $value, int $maxLength): string
    {
        return mb_strlen($value) > $maxLength
            ? mb_substr($value, 0, $maxLength).'...'
            : $value;
    }
}
