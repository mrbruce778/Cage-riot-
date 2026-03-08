<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $guarded = [];

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }
}
