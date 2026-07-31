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

 
public function payments()
    {
        return $this->hasMany(Payment::class);
    }
  
}