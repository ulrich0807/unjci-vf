<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationOtpMail;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException; // L'importation obligatoire pour gérer les dates

class MemberController extends Controller
{
    public function store(Request $request)
    {
        $this->normalizeApplicationInput($request);

        $memberId = $request->input('memberId');
        $existingMember = $memberId ? Member::with('user')->find($memberId) : null;
        $namePattern = "/^[\p{L}\p{M}](?:[\p{L}\p{M} '’-]*[\p{L}\p{M}])?$/u";
        $birthPlacePattern = "/^[\p{L}\p{M}\d](?:[\p{L}\p{M}\d '’-]*[\p{L}\p{M}\d])?$/u";
        $emailPattern = '/^[A-Z0-9._%+\-]+@[A-Z0-9](?:[A-Z0-9-]{0,61}[A-Z0-9])?(?:\.[A-Z0-9](?:[A-Z0-9-]{0,61}[A-Z0-9])?)*\.[A-Z]{2,63}$/i';
        $minimumNameLetters = function ($attribute, $value, $fail) {
            $letterCount = preg_match_all('/\p{L}/u', $value);

            if ($letterCount !== false && $letterCount >= 2) {
                return;
            }

            $label = match ($attribute) {
                'lastName' => 'Le nom',
                'firstName' => 'Le prénom',
                default => 'Le pseudonyme',
            };
            $fail("{$label} doit contenir au moins 2 lettres.");
        };

        $validated = $request->validate([
            'memberId' => 'nullable|integer|exists:members,id',
            'lastName' => ['bail', 'required', 'string', 'min:2', 'max:100', "regex:{$namePattern}", $minimumNameLetters],
            'firstName' => ['bail', 'required', 'string', 'min:2', 'max:100', "regex:{$namePattern}", $minimumNameLetters],
            'alias' => ['bail', 'nullable', 'string', 'min:2', 'max:100', "regex:{$namePattern}", $minimumNameLetters],
            'birthDate' => ['bail', 'required', 'date_format:d/m/Y', 'before_or_equal:today'],
            'birthPlace' => [
                'bail',
                'required',
                'string',
                'min:2',
                'max:100',
                "regex:{$birthPlacePattern}",
                function ($attribute, $value, $fail) {
                    $letterCount = preg_match_all('/\p{L}/u', $value);

                    if ($letterCount === false || $letterCount < 2) {
                        $fail('Le lieu de naissance doit contenir au moins 2 lettres.');
                    }
                },
            ],
            'phone' => [
                'bail',
                'required',
                'string',
                'max:40',
                'regex:/^[0-9+()\s-]+$/u',
                function ($attribute, $value, $fail) {
                    $digits = preg_replace('/\D/u', '', $value) ?? '';

                    if (strlen($digits) < 8 || strlen($digits) > 15) {
                        $fail('Le numéro de téléphone doit contenir entre 8 et 15 chiffres.');

                        return;
                    }

                    $plusPosition = strpos($value, '+');
                    $hasInvalidPlus = substr_count($value, '+') > 1
                        || ($plusPosition !== false
                            && $plusPosition !== 0
                            && ! str_starts_with($value, '(+'));

                    if ($hasInvalidPlus || ! $this->hasBalancedParentheses($value)) {
                        $fail('Le format du numéro de téléphone est invalide.');
                    }
                },
            ],
            'postalAddress' => 'nullable|string|max:255',
            'email' => [
                'bail',
                'required',
                'string',
                'max:255',
                'email',
                "regex:{$emailPattern}",
                Rule::unique('users', 'email')->ignore($existingMember?->user?->id),
            ],
            'requestType' => 'required|in:adhesion,renewal',
            'currentMemberNumber' => [
                $existingMember ? 'required' : 'nullable',
                'string',
                'regex:/^UJ\d{2}-\d{5}$/',
                Rule::unique('members', 'member_number')->ignore($existingMember?->id),
                Rule::unique('members', 'current_member_number')->ignore($existingMember?->id),
                Rule::unique('users', 'login')->ignore($existingMember?->user?->id),
            ],
            'professionalStatus' => 'required|string|max:255',
            'employers' => 'required|string|max:255',
            'mediaName' => 'required|string|max:255',
            'mediaType' => 'required|in:Écrit,Numérique',
            'functionTitle' => 'required|string|max:255',
            'pressCardNumber' => [
                'required', 'string', 'regex:/^\d{4}JP$/',
                Rule::unique('members', 'press_card_number')->ignore($existingMember?->id),
            ],
            'pressCardExpiry' => 'required|date',
            'oldCardRectoFile' => $request->input('requestType') === 'renewal' ? ($existingMember ? 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048' : 'required|file|mimes:pdf,jpg,jpeg,png|max:2048') : 'nullable',
            'oldCardVersoFile' => $request->input('requestType') === 'renewal' ? ($existingMember ? 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048' : 'required|file|mimes:pdf,jpg,jpeg,png|max:2048') : 'nullable',
            'pressCardRectoFile' => $existingMember ? 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048' : 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'pressCardVersoFile' => $existingMember ? 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048' : 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'photoFile' => $existingMember ? 'nullable|file|mimes:jpg,jpeg,png|max:2048' : 'required|file|mimes:jpg,jpeg,png|max:2048',
            'password' => 'required|string|min:8',
            'declarationAccepted' => 'required|boolean',
            'privacyAccepted' => 'required|boolean',
        ], [
            'lastName.required' => 'Le nom est obligatoire.',
            'lastName.min' => 'Le nom doit contenir au moins 2 lettres.',
            'lastName.max' => 'Le nom ne doit pas dépasser 100 caractères.',
            'lastName.regex' => 'Le nom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'firstName.required' => 'Le prénom est obligatoire.',
            'firstName.min' => 'Le prénom doit contenir au moins 2 lettres.',
            'firstName.max' => 'Le prénom ne doit pas dépasser 100 caractères.',
            'firstName.regex' => 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'alias.min' => 'Le pseudonyme doit contenir au moins 2 lettres.',
            'alias.max' => 'Le pseudonyme ne doit pas dépasser 100 caractères.',
            'alias.regex' => 'Le pseudonyme ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'birthDate.required' => 'La date de naissance est obligatoire.',
            'birthDate.date_format' => 'La date de naissance doit être une date valide au format JJ/MM/AAAA.',
            'birthDate.before_or_equal' => 'La date de naissance ne peut pas être dans le futur.',
            'birthPlace.required' => 'Le lieu de naissance est obligatoire.',
            'birthPlace.min' => 'Le lieu de naissance doit contenir au moins 2 caractères.',
            'birthPlace.max' => 'Le lieu de naissance ne doit pas dépasser 100 caractères.',
            'birthPlace.regex' => 'Le lieu de naissance contient des caractères non autorisés.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.max' => 'Le numéro de téléphone est trop long.',
            'phone.regex' => 'Le numéro de téléphone contient des caractères non autorisés.',
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.email' => 'L’adresse e-mail doit être valide.',
            'email.max' => 'L’adresse e-mail ne doit pas dépasser 255 caractères.',
            'email.regex' => 'L’adresse e-mail doit être au format nom@domaine.com.',
            'currentMemberNumber.regex' => 'Le numéro de membre UNJCI doit respecter le format UJ25-00122.',
            'currentMemberNumber.unique' => 'Ce numéro de membre UNJCI est déjà utilisé par un autre dossier.',
            'requestType.max' => 'Le type de demande ne doit pas dépasser 255 caractères.',
            'professionalStatus.max' => 'Le statut professionnel ne doit pas dépasser 255 caractères.',
            'employers.max' => 'L’entreprise de presse ne doit pas dépasser 255 caractères.',
            'functionTitle.max' => 'La fonction ne doit pas dépasser 255 caractères.',
        ]);

        $submittedMemberNumber = trim($validated['currentMemberNumber'] ?? '');
        if ($submittedMemberNumber !== '' && $validated['requestType'] !== 'renewal') {
            throw ValidationException::withMessages([
                'currentMemberNumber' => ['Un numéro de membre existant ne peut être proposé que pour un renouvellement.'],
            ]);
        }

        if ($existingMember) {
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
            if ($existingMember->status === 'approved' && $validated['requestType'] !== 'renewal') {
                 abort(403, 'Votre dossier a été validé. Vous ne pouvez plus modifier vos informations.');
            }

            $user = $existingMember->user;
            if (! $user) {
                return response()->json(['message' => 'Utilisateur associé introuvable.'], 404);
            }

            if (! Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['Le mot de passe actuel de ce compte est incorrect.'],
                ]);
            }

            $user->forceFill([
                'name' => $validated['firstName'].' '.$validated['lastName'],
                'email' => $validated['email'],
                'login' => $validated['currentMemberNumber'],
            ]);

            $user->save();

            // On exige toujours la vérification OTP lors d'un renouvellement
            $user->email_verified_at = null;
            $user->save();

            $pressCardRectoPath = $existingMember->press_card_recto;
            $pressCardVersoPath = $existingMember->press_card_verso;
            $photoPath = $existingMember->photo_file_path;
            $oldCardRectoPath = $existingMember->old_card_recto_path;
            $oldCardVersoPath = $existingMember->old_card_verso_path;

            if ($request->hasFile('pressCardRectoFile')) {
                $pressCardRectoPath = $request->file('pressCardRectoFile')->store('members/press_cards_recto', 'public');
            }
            if ($request->hasFile('pressCardVersoFile')) {
                $pressCardVersoPath = $request->file('pressCardVersoFile')->store('members/press_cards_verso', 'public');
            }
            if ($request->hasFile('oldCardRectoFile')) {
                $oldCardRectoPath = $request->file('oldCardRectoFile')->store('members/old_cards_recto', 'public');
            }
            if ($request->hasFile('oldCardVersoFile')) {
                $oldCardVersoPath = $request->file('oldCardVersoFile')->store('members/old_cards_verso', 'public');
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
                'request_type' => 'renewal',
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
                'old_card_recto_path' => $oldCardRectoPath,
                'old_card_verso_path' => $oldCardVersoPath,
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
        $oldCardRectoPath = $request->hasFile('oldCardRectoFile') ? $request->file('oldCardRectoFile')->store('members/old_cards_recto', 'public') : null;
        $oldCardVersoPath = $request->hasFile('oldCardVersoFile') ? $request->file('oldCardVersoFile')->store('members/old_cards_verso', 'public') : null;
        $photoPath = $request->file('photoFile')->store('members/photos', 'public');

        try {
            $member = DB::transaction(function () use ($validated, $pressCardRectoPath, $pressCardVersoPath, $oldCardRectoPath, $oldCardVersoPath, $photoPath, $submittedMemberNumber) {
                $proposedMemberNumber = $submittedMemberNumber !== '' ? $submittedMemberNumber : null;

                if ($proposedMemberNumber) {
                    // La proposition est réservée dans current_member_number, mais ne
                    // devient officielle qu'après la vérification de l'administrateur.
                    $this->ensureMemberNumberIsAvailable($proposedMemberNumber);
                }

                $user = User::create([
                    'name' => $validated['firstName'].' '.$validated['lastName'],
                    'email' => $validated['email'],
                    'login' => 'pending-'.bin2hex(random_bytes(16)),
                    'password' => Hash::make($validated['password']),
                    'role' => 'member',
                ]);

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
                    'current_member_number' => $proposedMemberNumber,
                    'member_number' => null,
                    'professional_status' => $validated['professionalStatus'],
                    'employers' => $validated['employers'],
                    'media_name' => $validated['mediaName'],
                    'media_type' => $validated['mediaType'],
                    'function_title' => $validated['functionTitle'],
                    'press_card_number' => $validated['pressCardNumber'],
                    'press_card_expiry' => $validated['pressCardExpiry'],
                    'press_card_recto' => $pressCardRectoPath,
                    'press_card_verso' => $pressCardVersoPath,
                    'old_card_recto_path' => $oldCardRectoPath,
                    'old_card_verso_path' => $oldCardVersoPath,
                    'photo_file_path' => $photoPath,
                    'declaration_accepted' => $validated['declarationAccepted'],
                    'privacy_accepted' => $validated['privacyAccepted'],
                ]);

                return $member->fresh();
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($submittedMemberNumber === '') {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'currentMemberNumber' => ['Ce numéro de membre UNJCI vient d’être réservé par un autre dossier.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie. Vous pouvez maintenant vous connecter avec votre adresse e-mail.',
            'data' => $member,
            'login_identifier' => $member->personal_email,
        ], 201);
    }

    public function uploadOldCards(Request $request)
    {
        $validated = $request->validate([
            'oldCardRectoFile' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'oldCardVersoFile' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $member = $user->member;
        if (!$member) {
            return response()->json(['message' => 'Profil membre introuvable.'], 404);
        }

        if ($member->status === 'approved') {
            return response()->json(['message' => 'Dossier validé, modification des documents impossible.'], 403);
        }

        $updates = [];

        if ($request->hasFile('oldCardRectoFile')) {
            $updates['old_card_recto_path'] = $request->file('oldCardRectoFile')->store('members/old_cards_recto', 'public');
        }

        if ($request->hasFile('oldCardVersoFile')) {
            $updates['old_card_verso_path'] = $request->file('oldCardVersoFile')->store('members/old_cards_verso', 'public');
        }

        if (count($updates) > 0) {
            $member->update($updates);
            return response()->json(['message' => 'Cartes ajoutées avec succès.', 'data' => $member], 200);
        }

        return response()->json(['message' => 'Aucun fichier fourni.'], 400);
    }

    public function findByCardNumber(string $cardNumber)
    {
        $member = Member::where('member_number', strtoupper(trim($cardNumber)))->first();

        if ($member) {
            // Le contrôle du mot de passe est effectué lors de la soumission du
            // formulaire. Le lookup public ne doit pas exposer les coordonnées
            // ni les pièces d'un compte existant.
            return response()->json([
                'id' => $member->id,
                'source' => 'existing',
                'member_number' => $member->member_number,
            ]);
        }

        return response()->json(['message' => 'Adhérent introuvable.'], 404);
    }

    private function normalizeApplicationInput(Request $request): void
    {
        $normalized = [];
        $collapseWhitespaceFields = [
            'lastName',
            'firstName',
            'alias',
            'birthPlace',
            'phone',
            'postalAddress',
            'requestType',
            'professionalStatus',
            'employers',
            'mediaName',
            'functionTitle',
        ];
        $trimOnlyFields = ['birthDate', 'email'];

        foreach (array_merge($collapseWhitespaceFields, $trimOnlyFields) as $field) {
            $value = $request->input($field);

            if (! is_string($value)) {
                continue;
            }

            if (in_array($field, $collapseWhitespaceFields, true)) {
                $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
            }

            $value = trim($value);
            $normalized[$field] = in_array($field, ['alias', 'postalAddress'], true) && $value === ''
                ? null
                : $value;
        }

        $memberNumber = $request->input('currentMemberNumber');
        if (is_string($memberNumber)) {
            $memberNumber = strtoupper(trim($memberNumber));
            $normalized['currentMemberNumber'] = $memberNumber === '' ? null : $memberNumber;
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    private function hasBalancedParentheses(string $value): bool
    {
        $balance = 0;

        foreach (str_split($value) as $character) {
            if ($character === '(') {
                $balance++;
            } elseif ($character === ')') {
                $balance--;

                if ($balance < 0) {
                    return false;
                }
            }
        }

        return $balance === 0;
    }

    private function ensureMemberNumberIsAvailable(string $memberNumber): void
    {
        $usedByUser = User::query()
            ->where('login', $memberNumber)
            ->lockForUpdate()
            ->exists();

        $usedByMember = Member::query()
            ->where(function ($query) use ($memberNumber) {
                $query->where('member_number', $memberNumber)
                    ->orWhere('current_member_number', $memberNumber);
            })
            ->lockForUpdate()
            ->exists();

        if ($usedByUser || $usedByMember) {
            throw ValidationException::withMessages([
                'currentMemberNumber' => ['Ce numéro de membre UNJCI est déjà utilisé.'],
            ]);
        }
    }

    public function sendEmailVerificationOtp(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $genericMessage = 'Si cette adresse correspond à une nouvelle adhésion, un code de vérification vient d’être envoyé.';
        $user = User::where('email', $validated['email'])->first();

        $verification = $user
            ? DB::table('email_verification_otps')->where('email', $user->email)->first()
            : null;

        if (! $user || $user->email_verified_at) {
            return response()->json(['message' => $genericMessage]);
        }

        if ($verification && $verification->last_sent_at && Carbon::parse($verification->last_sent_at)->gt(now()->subMinute())) {
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

    public function profile(Request $request)
    {
        $user = $request->user();
        $member = $user->member()->with(['payments' => function ($query) {
            $query->latest();
        }])->first();

        if (! $member) {
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
            'oldMemberCardFile' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $member = $request->user()->member;

        if (! $member) {
            return response()->json(['message' => 'Membre introuvable'], 404);
        }

        $memberNumber = $member->member_number ?: $member->current_member_number;
        $expectedPaymentType = $member->member_number || $member->request_type === 'renewal'
            ? 'renewal'
            : 'adhesion';

        if ($validated['paymentType'] !== $expectedPaymentType) {
            return response()->json([
                'message' => $expectedPaymentType === 'renewal'
                    ? 'Ce dossier correspond à un renouvellement : choisissez un renouvellement.'
                    : 'Ce nouveau dossier doit d’abord régler les frais d’adhésion.',
            ], 422);
        }

        if ($expectedPaymentType === 'adhesion' && $member->application_submitted_at) {
            return response()->json([
                'message' => 'Votre paiement est déjà confirmé et votre demande est en cours de traitement.',
            ], 422);
        }

        if ($expectedPaymentType === 'adhesion' && $member->status === 'approved') {
            return response()->json(['message' => 'Cette adhésion est déjà validée.'], 422);
        }

        if ($member->payments()->where('status', 'pending')->exists()) {
            return response()->json([
                'message' => 'Un paiement est déjà en attente de confirmation.',
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
            'previous_member_number' => $isRenewal ? $memberNumber : null,
            'old_member_card_path' => $oldMemberCardPath,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre paiement a été enregistré et doit maintenant être confirmé.',
            'payment' => $payment,
        ]);
    }
}
