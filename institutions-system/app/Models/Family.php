<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'head_of_family',
        'benefits',
    ];

    public function benefits()
    {
        return $this->hasMany(Benefit::class);
    }

    public function records()
    {
        return $this->hasMany(Record::class);
    }
}