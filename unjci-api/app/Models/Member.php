<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    // Autorise l'insertion en masse pour tous les champs sauf l'ID
    protected $guarded = ['id'];

    protected $appends = [
        'membership_stage',
        'card_is_valid',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'press_card_expiry' => 'date:Y-m-d',
        'membership_expires_at' => 'date',
        'application_submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function getMembershipStageAttribute(): string
    {
        if ($this->status === 'rejected') {
            return 'rejected';
        }

        // Une carte remise en examen conserve son numéro et son paiement initial.
        // Une autre tentative de paiement ne doit pas bloquer son réexamen.
        if ($this->status === 'pending' && $this->member_number && $this->application_submitted_at !== null) {
            return 'under_review';
        }

        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get(['payments.id', 'payments.status']);
        $latestPayment = $payments->sortByDesc('id')->first();

        if ($latestPayment?->status === 'pending') {
            return 'payment_pending';
        }

        if ($this->status === 'approved' || $this->approved_at !== null) {
            return 'approved';
        }

        if ($this->application_submitted_at !== null) {
            return 'under_review';
        }

        if ($latestPayment?->status === 'approved') {
            return 'under_review';
        }

        return 'awaiting_payment';
    }

    public function getCardIsValidAttribute(): bool
    {
        return $this->status === 'approved'
            && filled($this->member_number)
            && filled($this->qr_token)
            && $this->membership_expires_at !== null
            && $this->membership_expires_at->endOfDay()->gte(now());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
