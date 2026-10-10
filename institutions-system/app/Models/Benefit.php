<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Benefit extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'description',
        'amount',
        'date_awarded',
    ];

    public function family()
    {
        return $this->belongsTo(Family::class);
    }
}