<?php

namespace App\Models;

use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

class User extends Authenticatable
{
    public const ROLE_DEVELOPER = 'developer';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_DATA_ENTRY = 'data_entry';

    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'institution_id',
        'created_by',
        'permissions',
        'two_factor_code',
        'two_factor_expires_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'locked_until',
        'failed_login_attempts',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'two_factor_expires_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->role !== self::ROLE_DATA_ENTRY || ! $user->created_by) {
                return;
            }

            $creatorInstitutionId = self::query()
                ->whereKey($user->created_by)
                ->value('institution_id');

            if ($creatorInstitutionId !== null) {
                $user->institution_id = (int) $creatorInstitutionId;
            }
        });
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function dataEntryUsers(): HasMany
    {
        return $this->hasMany(self::class, 'created_by');
    }

    public function adminFamilies(): HasMany
    {
        return $this->hasMany(Family::class, 'administrator_id');
    }

    public function enteredFamilies(): HasMany
    {
        return $this->hasMany(Family::class, 'data_entry_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function reportTemplates(): HasMany
    {
        return $this->hasMany(ReportTemplate::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class, 'created_by');
    }

    public function isDeveloper(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isDataEntry(): bool
    {
        return $this->role === self::ROLE_DATA_ENTRY;
    }

    public function hasTotpEnabled(): bool
    {
        return ! empty($this->two_factor_secret);
    }

    public function getTotpSecret(): ?string
    {
        if (empty($this->two_factor_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $this->two_factor_secret);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setTotpSecret(?string $secret): void
    {
        $this->two_factor_secret = $secret ? Crypt::encryptString($secret) : null;
    }

    public function getRecoveryCodes(): array
    {
        if (empty($this->two_factor_recovery_codes)) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString((string) $this->two_factor_recovery_codes), true);
            return is_array($decoded) ? array_values($decoded) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function setRecoveryCodes(array $codes): void
    {
        $this->two_factor_recovery_codes = Crypt::encryptString(json_encode(array_values($codes), JSON_THROW_ON_ERROR));
    }

    public function consumeRecoveryCode(string $code): bool
    {
        $normalized = strtolower(trim($code));
        $codes = $this->getRecoveryCodes();

        foreach ($codes as $index => $savedCode) {
            if (hash_equals(strtolower((string) $savedCode), $normalized)) {
                unset($codes[$index]);
                $this->setRecoveryCodes(array_values($codes));
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        $defaults = $this->defaultPermissions();
        $overrides = $this->permissions ?? [];

        if (array_key_exists($permission, $overrides)) {
            return (bool) $overrides[$permission];
        }

        return (bool) ($defaults[$permission] ?? false);
    }

    public function defaultPermissions(): array
    {
        $developerDefaults = [
            'manage_admins' => true,
            'manage_data_entries' => true,
            'manage_families' => true,
            'view_all_families' => true,
            'edit_families' => true,
            'delete_families' => true,
            'export_reports' => true,
            'import_families' => true,
            'view_activity_logs' => true,
        ];

        $adminDefaults = [
            'manage_admins' => false,
            'manage_data_entries' => true,
            'manage_families' => true,
            'view_all_families' => true,
            'edit_families' => true,
            'delete_families' => true,
            'export_reports' => true,
            'import_families' => true,
            'view_activity_logs' => true,
        ];

        $dataEntryDefaults = [
            'manage_admins' => false,
            'manage_data_entries' => false,
            'manage_families' => true,
            'view_all_families' => true,
            'edit_families' => true,
            'delete_families' => false,
            'export_reports' => false,
            'import_families' => false,
            'view_activity_logs' => false,
        ];

        $adminDefaults = array_replace($adminDefaults, SystemSettings::getBoolMap('default_permissions_admin'));
        $dataEntryDefaults = array_replace($dataEntryDefaults, SystemSettings::getBoolMap('default_permissions_data_entry'));

        return match ($this->role) {
            self::ROLE_DEVELOPER => $developerDefaults,
            self::ROLE_ADMIN => $adminDefaults,
            self::ROLE_DATA_ENTRY => $dataEntryDefaults,
            default => [],
        };
    }
}
