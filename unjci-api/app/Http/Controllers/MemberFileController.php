<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemberFileController extends Controller
{
    public function show(Request $request, string $path)
    {
        abort_if(str_contains($path, '..') || str_contains($path, '\\') || ! str_starts_with($path, 'members/'), 404);
        $member = Member::query()->where(function ($query) use ($path) {
            foreach (['photo_file_path', 'press_card_recto', 'press_card_verso', 'old_card_recto_path', 'old_card_verso_path'] as $column) {
                $query->orWhere($column, $path);
            }
        })->first();
        $payment = $member ? null : Payment::where('old_member_card_path', $path)->first();
        $member ??= $payment?->member;
        abort_unless($member, 404);
        $user = $request->user();
        $isPhoto = $path === $member->photo_file_path;
        $canRead = $user->id === $member->user_id
            || $user->hasPermission('manage_members')
            || ($payment && ($user->hasPermission('manage_payments') || $user->hasPermission('view_payment_history')))
            || ($isPhoto && in_array($user->role, ['admin', 'scanner'], true));
        abort_unless($canRead, 403);

        // Les documents historiques restent lisibles par cette route protégée.
        $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($path), 404);
        return response()->file(Storage::disk($disk)->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'",
        ]);
    }
}
