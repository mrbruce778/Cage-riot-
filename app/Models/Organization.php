<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Tenant;

class Organization extends Model
{
    use HasFactory;

    protected $guarded = [];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function parent()
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    public function userRoles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot('role_id')
            ->withTimestamps();
    }
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
