<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'institution_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function families()
    {
        return $this->hasMany(Family::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isAdministrator()
    {
        return $this->role === 'administrator';
    }

    public function isDataEntry()
    {
        return $this->role === 'data_entry';
    }
}