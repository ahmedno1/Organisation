<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Orphan extends Model
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
        'guardian_name',
        'guardian_national_id',
        'guardian_mobile',
        'guardian_relation',
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
        'chronic_diseases',
        'disability',
        'notes',
        'head_age',
        'orphan_photo_path',
        'death_certificate_path',
        'supporting_documents',
        'members_payload',
    ];

    protected $casts = [
        'head_birth_date' => 'date',
        'father_death_date' => 'date',
        'mother_death_date' => 'date',
        'supporting_documents' => 'array',
        'members_payload' => 'array',
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
}
