<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreloadedMember extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['claimed_at' => 'datetime'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
