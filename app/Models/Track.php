<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    protected $guarded = [];

    /**
     * A track belongs to a release
     */
    public function release()
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Optional: who created the track
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}