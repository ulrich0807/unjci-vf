<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Support\Facades\Storage;

class CardVerificationController extends Controller
{
    public function show(string $token)
    {
        $member = $this->validMember($token);

        return response()->json([
            'success' => true,
            'member' => [
                ...$member->only(['first_name', 'last_name', 'member_number', 'membership_expires_at']),
                'has_photo' => filled($member->photo_file_path),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function photo(string $token)
    {
        $member = $this->validMember($token);
        $path = $member->photo_file_path;
        abort_unless(is_string($path) && str_starts_with($path, 'members/')
            && ! str_contains($path, '..') && ! str_contains($path, '\\'), 404);

        $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return response()->file(Storage::disk($disk)->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'",
        ]);
    }

    private function validMember(string $token): Member
    {
        $member = Member::where('qr_token', $token)->firstOrFail();
        abort_unless($member->card_is_valid, 422, 'Cette carte est expirée ou son adhésion n’est pas approuvée.');

        return $member;
    }
}
