<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Member;
use App\Models\PreloadedMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use App\Mail\ApplicationSubmitted;
use App\Mail\EmailVerificationOtpMail;
use Carbon\Carbon; // L'importation obligatoire pour gérer les dates

class MemberController extends Controller
{
    public function checkEmailAvailability(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'memberId' => 'nullable|integer|exists:members,id',
        ]);

        $query = User::where('email', $validated['email']);
        if (! empty($validated['memberId'])) {
            $member = Member::find($validated['memberId']);
            if ($member?->user) {
                $query->where('id', '!=', $member->user->id);
            }
        }

        return response()->json([
            'available' => ! $query->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $memberId = $request->input('memberId');
        $existingMember = $memberId ? Member::with('user')->find($memberId) : null;

        $validated = $request->validate([
            'memberId' => 'nullable|integer|exists:members,id',
            'preloadedMemberId' => 'nullable|integer|exists:preloaded_members,id',
            'lastName' => 'required|string|max:255',
            'firstName' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'birthDate' => 'required|date_format:d/m/Y',
            'birthPlace' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'postalAddress' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                
                Rule::unique('users', 'email')->ignore($existingMember?->user?->id),
            ],
            'requestType' => 'required|string',
            'currentMemberNumber' => [
                $existingMember ? 'required' : 'nullable', 'string', 'regex:/^UJ\d{2}-\d{5}$/',
                Rule::unique('members', 'member_number')->ignore($existingMember?->id),
                Rule::unique('users', 'login')->ignore($existingMember?->user?->id),
            ],
            'professionalStatus' => 'required|string',
            'employers' => 'required|string',
            'mediaName' => 'required|string|max:255',
            'mediaType' => 'required|in:Écrit,Numérique',
            'functionTitle' => 'required|string',
            'pressCardNumber' => [
                'required', 'string', 'regex:/^\d{4}JP$/',
                Rule::unique('members', 'press_card_number')->ignore($existingMember?->id),
            ],
            'pressCardExpiry' => 'required|date',
            'pressCardRectoFile' => $existingMember ? 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048' : 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'pressCardVersoFile' => $existingMember ? 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048' : 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'photoFile' => $existingMember ? 'nullable|file|mimes:jpg,jpeg,png|max:2048' : 'required|file|mimes:jpg,jpeg,png|max:2048',
            'password' => $existingMember ? 'nullable|string|min:8' : 'required|string|min:8',
            'declarationAccepted' => 'required|boolean',
            'privacyAccepted' => 'required|boolean',
        ]);

        if ($existingMember) {
            $submittedMemberNumber = trim($validated['currentMemberNumber'] ?? '');
            $numberMatches = in_array($submittedMemberNumber, array_filter([
                $existingMember->member_number,
                $existingMember->current_member_number,
            ]), true);

            if (! $numberMatches) {
                return response()->json([
                    'message' => 'Le numéro de carte membre ne correspond pas au dossier demandé.',
                ], 422);
            }
        }

        if ($existingMember) {
            $user = $existingMember->user;
            if (! $user) {
                return response()->json(['message' => 'Utilisateur associé introuvable.'], 404);
            }

            $user->forceFill([
                'name' => $validated['firstName'] . ' ' . $validated['lastName'],
                'email' => $validated['email'],
                'login' => $validated['currentMemberNumber'],
            ]);

            if (! empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            if ($user->wasChanged('email')) {
                $user->email_verified_at = null;
                $user->save();
            }

            $pressCardRectoPath = $existingMember->press_card_recto;
            $pressCardVersoPath = $existingMember->press_card_verso;
            $photoPath = $existingMember->photo_file_path;

            if ($request->hasFile('pressCardRectoFile')) {
                $pressCardRectoPath = $request->file('pressCardRectoFile')->store('members/press_cards_recto', 'public');
            }
            if ($request->hasFile('pressCardVersoFile')) {
                $pressCardVersoPath = $request->file('pressCardVersoFile')->store('members/press_cards_verso', 'public');
            }
            if ($request->hasFile('photoFile')) {
                $photoPath = $request->file('photoFile')->store('members/photos', 'public');
            }

            $existingMember->update([
                'last_name' => $validated['lastName'],
                'first_name' => $validated['firstName'],
                'alias' => $validated['alias'] ?? null,
                'birth_date' => Carbon::createFromFormat('d/m/Y', $validated['birthDate'])->format('Y-m-d'),
                'birth_place' => $validated['birthPlace'],
                'phone' => $validated['phone'],
                'postal_address' => $validated['postalAddress'] ?? null,
                'personal_email' => $validated['email'],
                'request_type' => $validated['requestType'],
                'current_member_number' => $validated['currentMemberNumber'],
                'member_number' => $validated['currentMemberNumber'],
                'professional_status' => $validated['professionalStatus'],
                'employers' => $validated['employers'],
                'media_name' => $validated['mediaName'],
                'media_type' => $validated['mediaType'],
                'function_title' => $validated['functionTitle'],
                'press_card_number' => $validated['pressCardNumber'],
                'press_card_expiry' => $validated['pressCardExpiry'],
                'press_card_recto' => $pressCardRectoPath,
                'press_card_verso' => $pressCardVersoPath,
                'photo_file_path' => $photoPath,
                'declaration_accepted' => $validated['declarationAccepted'],
                'privacy_accepted' => $validated['privacyAccepted'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Données du membre mises à jour avec succès.',
                'data' => $existingMember,
            ]);
        }

        $pressCardRectoPath = $request->file('pressCardRectoFile')->store('members/press_cards_recto', 'public');
        $pressCardVersoPath = $request->file('pressCardVersoFile')->store('members/press_cards_verso', 'public');
        $photoPath = $request->file('photoFile')->store('members/photos', 'public');

        $member = DB::transaction(function () use ($validated, $pressCardRectoPath, $pressCardVersoPath, $photoPath) {
            $preloaded = ! empty($validated['preloadedMemberId'])
                ? PreloadedMember::lockForUpdate()->find($validated['preloadedMemberId'])
                : null;

            if ($preloaded && ($preloaded->claimed_at || $preloaded->member_id)) {
                abort(422, 'Cette fiche importée a déjà été utilisée.');
            }

            if ($preloaded && $preloaded->member_number !== ($validated['currentMemberNumber'] ?? null)) {
                abort(422, 'Le numéro UNJCI ne correspond pas à la fiche importée.');
            }

            $memberNumber = $preloaded?->member_number ?: $this->nextMemberNumber();

            $user = User::create([
                'name' => $validated['firstName'] . ' ' . $validated['lastName'],
                'email' => $validated['email'],
                'login' => 'pending-' . bin2hex(random_bytes(16)),
                'password' => Hash::make($validated['password']),
                'role' => 'member',
            ]);

            DB::table('email_verification_otps')->updateOrInsert(
                ['email' => $user->email],
                [
                    'code_hash' => Hash::make('verification-pending'),
                    'expires_at' => now(),
                    'attempts' => 0,
                    'last_sent_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $member = Member::create([
                'user_id' => $user->id,
                'last_name' => $validated['lastName'],
                'first_name' => $validated['firstName'],
                'alias' => $validated['alias'] ?? null,
                'birth_date' => Carbon::createFromFormat('d/m/Y', $validated['birthDate'])->format('Y-m-d'),
                'birth_place' => $validated['birthPlace'],
                'phone' => $validated['phone'],
                'postal_address' => $validated['postalAddress'] ?? null,
                'personal_email' => $validated['email'],
                'request_type' => $validated['requestType'],
                'current_member_number' => $memberNumber,
                'professional_status' => $validated['professionalStatus'],
                'employers' => $validated['employers'],
                'media_name' => $validated['mediaName'],
                'media_type' => $validated['mediaType'],
                'function_title' => $validated['functionTitle'],
                'press_card_number' => $validated['pressCardNumber'],
                'press_card_expiry' => $validated['pressCardExpiry'],
                'press_card_recto' => $pressCardRectoPath,
                'press_card_verso' => $pressCardVersoPath,
                'photo_file_path' => $photoPath,
                'declaration_accepted' => $validated['declarationAccepted'],
                'privacy_accepted' => $validated['privacyAccepted'],
            ]);

            $member->update(['member_number' => $memberNumber]);
            $user->update(['login' => $memberNumber]);
            $preloaded?->update(['member_id' => $member->id, 'claimed_at' => now()]);

            return $member->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie.',
            'data' => $member,
            'email_verification_required' => true,
        ], 201);
    }

    public function findByCardNumber(string $cardNumber)
    {
        $member = Member::where('member_number', $cardNumber)
            ->orWhere('current_member_number', $cardNumber)
            ->first();

        if ($member) {
            return response()->json($member);
        }

        $preloaded = PreloadedMember::where('member_number', $cardNumber)->whereNull('claimed_at')->first();
        if (! $preloaded) {
            return response()->json(['message' => 'Adhérent introuvable.'], 404);
        }

        $nameParts = preg_split('/\s+/u', trim($preloaded->full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $fallbackLastName = array_shift($nameParts) ?: null;
        $fallbackFirstNames = $nameParts ? implode(' ', $nameParts) : null;

        return response()->json([
            'preloaded_member_id' => $preloaded->id,
            'full_name' => $preloaded->full_name,
            'last_name' => $preloaded->suggested_last_name ?: $fallbackLastName,
            'first_name' => $preloaded->suggested_first_names ?: $fallbackFirstNames,
            'member_number' => $preloaded->member_number,
            'press_card_number' => $preloaded->press_card_number,
            'employers' => $preloaded->company_name,
            'media_name' => $preloaded->media_name,
            'media_type' => $preloaded->media_type,
            'mapping_status' => $preloaded->mapping_status,
            'source' => 'preloaded',
        ]);
    }

    private function nextMemberNumber(): string
    {
        $prefix = 'UJ' . now()->format('y') . '-';
        $numbers = Member::query()->lockForUpdate()->where('member_number', 'like', $prefix . '%')->pluck('member_number')
            ->merge(PreloadedMember::query()->lockForUpdate()->where('member_number', 'like', $prefix . '%')->pluck('member_number'));

        $lastSequence = $numbers->map(function ($number) use ($prefix) {
            return preg_match('/^' . preg_quote($prefix, '/') . '(\d{5})$/', $number, $matches)
                ? (int) $matches[1]
                : 0;
        })->max() ?? 0;

        abort_if($lastSequence >= 99999, 422, 'Aucun nouveau numéro UNJCI n’est disponible pour cette année.');
        return $prefix . str_pad((string) ($lastSequence + 1), 5, '0', STR_PAD_LEFT);
    }

    public function sendEmailVerificationOtp(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $genericMessage = 'Si cette adresse correspond à une nouvelle adhésion, un code de vérification vient d’être envoyé.';
        $user = User::where('email', $validated['email'])->first();

        $verification = $user
            ? DB::table('email_verification_otps')->where('email', $user->email)->first()
            : null;

        if (! $user || $user->email_verified_at || ! $verification) {
            return response()->json(['message' => $genericMessage]);
        }

        if ($verification->last_sent_at && Carbon::parse($verification->last_sent_at)->gt(now()->subMinute())) {
            return response()->json([
                'message' => 'Un code vient déjà d’être envoyé. Patientez une minute avant un nouvel envoi.',
            ], 429);
        }

        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $code) {
            DB::table('email_verification_otps')->updateOrInsert(
                ['email' => $user->email],
                [
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes(10),
                    'attempts' => 0,
                    'last_sent_at' => now(),
                    'updated_at' => now(),
                ]
            );
            Mail::to($user->email)->send(new EmailVerificationOtpMail($user, $code));
        });

        return response()->json(['message' => $genericMessage]);
    }

    public function verifyEmailOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            $user = User::where('email', $validated['email'])->first();
            $verification = DB::table('email_verification_otps')
                ->where('email', $validated['email'])
                ->lockForUpdate()
                ->first();

            if (! $user || ! $verification) {
                return ['error' => 'Code invalide ou expiré.'];
            }

            if (Carbon::parse($verification->expires_at)->isPast()) {
                return ['error' => 'Ce code a expiré. Demandez un nouveau code.'];
            }

            if ($verification->attempts >= 5) {
                return ['error' => 'Trop de codes incorrects. Demandez un nouveau code.'];
            }

            if (! Hash::check($validated['code'], $verification->code_hash)) {
                DB::table('email_verification_otps')->where('email', $validated['email'])->increment('attempts');
                return ['error' => 'Le code saisi est incorrect.'];
            }

            $user->forceFill(['email_verified_at' => now()])->save();
            DB::table('email_verification_otps')->where('email', $user->email)->delete();

            return ['member_id' => $user->member?->id];
        });

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre adresse e-mail a été vérifiée.',
            'member_id' => $result['member_id'],
        ]);
    }

    public function sendApplicationConfirmation(Member $member)
    {
        try {
            Mail::to($member->personal_email)->send(new ApplicationSubmitted($member));

            return response()->json(['success' => true]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'envoyer l\'e-mail de confirmation.',
            ], 500);
        }
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $member = $user->member()->with(['payments' => function($query) {
            $query->latest();
        }])->first();

        if (!$member) {
            return response()->json(['message' => 'Aucun dossier membre associé.'], 404);
        }

        return response()->json($member);
    }

    public function submitPayment(Request $request)
    {
        $validated = $request->validate([
            'paymentPhone' => 'required|string',
            'transactionId' => 'required|string|unique:payments,transaction_id',
            'paymentType' => 'required|in:adhesion,renewal',
            'previousMemberNumber' => 'required_if:paymentType,renewal|nullable|string|max:100',
            'oldMemberCardFile' => 'required_if:paymentType,renewal|nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $member = $request->user()->member;

        if (!$member) {
            return response()->json(['message' => 'Membre introuvable'], 404);
        }

        if ($member->payments()->where('status', 'pending')->exists()) {
            return response()->json([
                'message' => 'Un paiement est dÃ©jÃ  en attente de validation.',
            ], 422);
        }

        $isRenewal = $validated['paymentType'] === 'renewal';
        $oldMemberCardPath = $request->file('oldMemberCardFile')
            ? $request->file('oldMemberCardFile')->store('members/old_member_cards', 'public')
            : null;

        $payment = $member->payments()->create([
            'amount' => $isRenewal ? 5000 : 10000,
            'payment_phone' => $validated['paymentPhone'],
            'transaction_id' => $validated['transactionId'],
            'payment_type' => $validated['paymentType'],
            'previous_member_number' => $validated['previousMemberNumber'] ?? null,
            'old_member_card_path' => $oldMemberCardPath,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'payment' => $payment
        ]);
    }
}
