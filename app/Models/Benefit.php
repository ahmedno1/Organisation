<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Benefit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'benefit_date',
        'supporter_name',
        'target_category',
    ];

    protected $casts = [
        'benefit_date' => 'date',
    ];

    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class);
    }

    public function familyMembers(): BelongsToMany
    {
        return $this->belongsToMany(FamilyMember::class, 'benefit_family_member');
    }
}
