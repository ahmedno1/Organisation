<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'administrator_id',
        'data_entry_id',
        'head_name',
        'head_national_id',
        'head_gender',
        'head_birth_date',
        'marital_status',
        'spouse_name',
        'spouse_national_id',
        'spouse_birth_date',
        'spouse_pregnant',
        'mother_name',
        'mother_national_id',
        'mother_marital_status',
        'guardian_name',
        'guardian_national_id',
        'guardian_mobile',
        'siblings_count',
        'father_death_date',
        'mother_death_date',
        'education_status',
        'detailed_address',
        'contact_phones',
        'governorate',
        'area',
        'family_members_count',
        'mobile',
        'war_injury',
        'chronic_diseases',
        'disability',
        'employment_status',
        'notes',
        'head_age',
        'spouse_age',
        'benefits_from_institution',
    ];

    protected $casts = [
        'head_birth_date' => 'date',
        'spouse_birth_date' => 'date',
        'spouse_pregnant' => 'boolean',
        'father_death_date' => 'date',
        'mother_death_date' => 'date',
        'war_injury' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }

    public function dataEntry(): BelongsTo
    {
        return $this->belongsTo(User::class, 'data_entry_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function benefits(): BelongsToMany
    {
        return $this->belongsToMany(Benefit::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(FamilyTask::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FamilyAttachment::class);
    }

    protected function priorityScore(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                $score = 0;

                $score += min((int) $this->family_members_count, 15) * 2;

                if ((bool) $this->war_injury) {
                    $score += 20;
                }

                if (! empty($this->chronic_diseases)) {
                    $score += 18;
                }

                if (! empty($this->disability)) {
                    $score += 22;
                }

                if ((int) ($this->head_age ?? 0) > 0 && (int) $this->head_age < 18) {
                    $score += 15;
                }

                if ((string) ($this->head_gender ?? '') === 'female') {
                    $score += 12;
                }

                if (in_array((string) $this->marital_status, ['ارمل', 'ارملة'], true)) {
                    $score += 12;
                }

                return $score;
            }
        );
    }
}
