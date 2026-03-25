<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Release extends Model
{
    use HasUuids;

    protected $table = 'releases';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded =[];

    protected $casts = [
        'release_date' => 'date',
        'original_release_date' => 'date',
        'metadata' => 'array',
];
    public function tracks()
    {
        return $this->hasMany(Track::class, 'release_id');
    }

    public function artwork()
    {
        return $this->belongsTo(Asset::class, 'artwork_asset_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
