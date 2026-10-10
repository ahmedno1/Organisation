<?php

namespace App\Support;

use App\Models\AppSetting;

class SystemSettings
{
    /**
     * @var array<string, string|null>
     */
    private static array $cache = [];

    public static function getString(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key] ?? $default;
        }

        try {
            $value = AppSetting::query()->where('key', $key)->value('value');
        } catch (\Throwable) {
            $value = null;
        }

        self::$cache[$key] = $value;

        return $value ?? $default;
    }

    /**
     * @return array<string, bool>
     */
    public static function getBoolMap(string $key): array
    {
        $raw = self::getString($key);
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $k => $v) {
            $result[(string) $k] = (bool) $v;
        }

        return $result;
    }

    public static function putString(string $key, ?string $value): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        self::$cache[$key] = $value;
    }

    /**
     * @param array<string, bool> $map
     */
    public static function putBoolMap(string $key, array $map): void
    {
        self::putString($key, json_encode($map, JSON_UNESCAPED_UNICODE));
    }
}
