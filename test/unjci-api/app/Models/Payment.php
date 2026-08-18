<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'member_id',
        'amount',
        'payment_phone',
        'transaction_id',
        'payment_type',
        'previous_member_number',
        'old_member_card_path',
        'status',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
