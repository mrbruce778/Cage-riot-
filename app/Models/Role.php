<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'allowed_org_type',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function userRoles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot('organization_id')
            ->withTimestamps();
    }
}
