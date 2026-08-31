<?php

namespace App\Http\Controllers;

use App\Mail\TemporaryPasswordMail;
use App\Models\LoginAudit;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validation des données reçues d'Angular
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        // Un nouvel adhérent se connecte d'abord avec son e-mail. Après
        // approbation, son numéro UNJCI devient également un identifiant valide.
        $identifier = trim($request->login);
        $user = User::whereRaw('LOWER(email) = ?', [Str::lower($identifier)])->first();

        if (! $user) {
            $member = Member::with('user')
                ->where('member_number', $identifier)
                ->first();

            // Un numéro seulement proposé dans current_member_number ne devient
            // un identifiant qu'après sa confirmation par l'administration.
            // Les comptes historiques et techniques conservent aussi leur login.
            $user = $member?->user ?: User::where('login', $identifier)->first();
        }

        // 3. Tentative de connexion
        // Auth::attempt va chercher l'utilisateur et vérifier que le mot de passe correspond au hash en BDD
        if ($user && Hash::check($request->password, $user->password)) {
            // Vérification que l'e-mail est bien vérifié avant d'autoriser la connexion
            if (is_null($user->email_verified_at) && !in_array($user->role, ['admin', 'media_admin', 'scanner'])) {
                $this->recordLoginAudit($request, $identifier, false, 'unverified_email', $user);
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez vérifier votre adresse e-mail via le code (OTP) avant de vous connecter.',
                    'needs_verification' => true,
                    'email' => $user->email,
                ], 403);
            }

            // 4. Génération du Token de sécurité
            $token = $user->createToken('auth_token')->plainTextToken;
            $this->recordLoginAudit($request, $identifier, true, null, $user);

            // 5. On renvoie les infos exactes qu'Angular attend !
            return response()->json([
                'success' => true,
                'user' => $user, // Contient le 'role' pour la redirection Angular
                'token' => $token,
                'must_change_password' => (bool) $user->must_change_password,
            ]);
        }

        // Si échec de la connexion
        $this->recordLoginAudit($request, $identifier, false, 'invalid_credentials', $user);

        return response()->json([
            'success' => false,
            'message' => 'Adresse e-mail, numéro UNJCI ou mot de passe incorrect.',
        ], 401); // 401 = Unauthorized
    }

    private function recordLoginAudit(
        Request $request,
        string $login,
        bool $success,
        ?string $failureReason,
        ?User $user,
    ): void {
        try {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            LoginAudit::create([
                'user_id' => $user?->id,
                'login' => Str::limit($login, 255, ''),
                'success' => $success,
                'failure_reason' => $failureReason,
                'ip_address' => $ipAddress ? Str::limit($ipAddress, 45, '') : null,
                'user_agent' => $userAgent ? Str::limit($userAgent, 1000, '') : null,
            ]);
        } catch (Throwable $exception) {
            // L'audit est volontairement best-effort : la connexion reste prioritaire.
            try {
                logger()->warning('Impossible d’enregistrer le journal de connexion.', [
                    'error' => Str::limit($exception->getMessage(), 300, ''),
                ]);
            } catch (Throwable) {
                // Ne jamais transformer une erreur d'audit en erreur de connexion.
            }
        }
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate(
            ['email' => ['required', 'email']],
            [
                'email.required' => 'Saisissez votre adresse e-mail.',
                'email.email' => 'Saisissez une adresse e-mail valide.',
            ],
        );
        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'code' => 'email_not_found',
                'message' => 'Aucun compte n’est associé à cette adresse e-mail. Vérifiez l’adresse saisie ou contactez l’UNJCI.',
            ], 404);
        }

        $temporaryPassword = Str::password(12, symbols: false);
        DB::transaction(function () use ($user, $temporaryPassword) {
            $user->forceFill(['password' => Hash::make($temporaryPassword)])->save();
            Mail::to($user->email)->send(new TemporaryPasswordMail($user, $temporaryPassword));
            $user->tokens()->delete();
        });

        return response()->json([
            'message' => 'Un mot de passe temporaire a été envoyé à cette adresse. Consultez votre boîte de réception et vos courriers indésirables.',
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.confirmed' => 'La confirmation du nouveau mot de passe ne correspond pas.',
        ]);

        $user = $request->user();

        if (in_array($user->role, ['admin', 'media_admin'])) {
            if (!$user->must_change_password) {
                abort(403, 'Vous ne pouvez plus modifier votre mot de passe après la première connexion.');
            }
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        return response()->json(['message' => 'Votre mot de passe a été modifié.']);
    }
}
