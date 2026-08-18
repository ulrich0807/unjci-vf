<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Member extends Model
{
    use HasFactory;

    // Autorise l'insertion en masse pour tous les champs sauf l'ID
    protected $guarded = ['id'];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'press_card_expiry' => 'date:Y-m-d',
        'membership_expires_at' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

 
public function payments()
    {
        return $this->hasMany(Payment::class);
    }
  
}
