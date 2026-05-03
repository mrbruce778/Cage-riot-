<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | JWT Methods (Required)
    |--------------------------------------------------------------------------
    */

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        $userRole = $this->userRoles()->with('organization')->first();

        if (!$userRole) {
            return [];
        }

        return [
            'organization_id' => $userRole->organization_id,
            'role' => $userRole->role->name ?? null,
            'org_type' => $userRole->organization->type,
        ];
    }
    public function userRoles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'user_roles')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('organization_id')
            ->withTimestamps();
    }
    public function hasRoleInOrganization($roleName, $organizationId)
    {
        return $this->userRoles()
            ->where('organization_id', $organizationId)
            ->whereHas('role', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            })
            ->exists();
    }
    public function currentOrganizationId()
    {
        return auth()->payload()->get('organization_id');
    }
    public function scopeWithArtistRoles($query, $organizationId)
    {
        return $query->whereHas('userRoles', function ($q) use ($organizationId) {
            $q->where(function ($sub) use ($organizationId) {
                $sub->where('organization_id', $organizationId)
                    ->orWhereHas('organization', function ($org) use ($organizationId) {
                        $org->where('parent_id', $organizationId);
                    });
            })
            ->whereHas('role', function ($role) {
                $role->whereIn('name', [
                    'standard_owner',
                    'standard_viewer',
                    'artist_owner',
                    'artist_viewer'
                ]);
            });
        });
    }
}
