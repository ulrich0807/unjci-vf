<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;

class MemberNumberAllocator
{
    /**
     * Reserve the next yearly UNJCI number.
     *
     * This method must be called from the transaction that approves the member.
     */
    public function next(): string
    {
        $prefix = 'UJ'.now()->format('y').'-';
        $numbers = User::query()->lockForUpdate()->where('login', 'like', $prefix.'%')->pluck('login')
            ->merge(Member::query()->lockForUpdate()->where('member_number', 'like', $prefix.'%')->pluck('member_number'))
            ->merge(Member::query()->lockForUpdate()->where('current_member_number', 'like', $prefix.'%')->pluck('current_member_number'));

        $lastSequence = $numbers->map(function ($number) use ($prefix) {
            return preg_match('/^'.preg_quote($prefix, '/').'(\d{5})$/', (string) $number, $matches)
                ? (int) $matches[1]
                : 0;
        })->max() ?? 0;

        abort_if($lastSequence >= 99999, 422, 'Aucun nouveau numéro UNJCI n’est disponible pour cette année.');

        return $prefix.str_pad((string) ($lastSequence + 1), 5, '0', STR_PAD_LEFT);
    }
}
