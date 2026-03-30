<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Track extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $keyType = 'string';
    public $incrementing = false;

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function release()
    {
        return $this->belongsTo(Release::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function audio()
    {
        return $this->hasOne(Asset::class)->where('asset_type', 'audio');
    }

    public function artwork()
    {
        return $this->hasOne(Asset::class)->where('asset_type', 'artwork');
    }

    /*
    |--------------------------------------------------------------------------
    | Boot Logic
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::creating(function ($track) {
            if (!$track->track_number) {
                $max = self::where('release_id', $track->release_id)->max('track_number');
                $track->track_number = $max ? $max + 1 : 1;
            }
        });
    }
}