<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Payment;
use Illuminate\Support\Str;

class PaymentApproval
{
    /** Appeler dans la transaction, une seule fois par paiement confirmé. */
    public function apply(Payment $payment): ?Member
    {
        $member = Member::lockForUpdate()->findOrFail($payment->member_id);
        if ($payment->payment_type === 'renewal' && $member->member_number && $member->status === 'approved') {
            $base = $member->membership_expires_at?->isFuture() ? $member->membership_expires_at : today();
            $member->update([
                'membership_expires_at' => $base->copy()->addYear(),
                'qr_token' => $member->qr_token ?: Str::random(40),
            ]);
            return null;
        }
        if (! $member->application_submitted_at) {
            // Un encaissement ne doit pas annuler une décision de rejet.
            $member->update(['application_submitted_at' => now()]);
            return $member->status === 'rejected' ? null : $member;
        }
        return null;
    }
}
