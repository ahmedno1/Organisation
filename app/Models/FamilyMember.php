<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'relation',
        'name',
        'national_id',
        'birth_date',
        'birth_place',
        'mobile',
        'head_age',
        'member_age',
        'health_status',
        'chronic_diseases',
        'disability',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FamilyAttachment::class, 'family_member_id');
    }

    public function benefits(): BelongsToMany
    {
        return $this->belongsToMany(Benefit::class, 'benefit_family_member');
    }
}
