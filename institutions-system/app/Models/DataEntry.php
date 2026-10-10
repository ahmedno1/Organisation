<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'administrator_id',
        'name',
    ];

    public function administrator()
    {
        return $this->belongsTo(Administrator::class);
    }
}