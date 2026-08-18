<?php

namespace App\Http\Controllers;

use App\Mail\TemporaryPasswordMail;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validation des données reçues d'Angular
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        // 2. Vérifier si l'utilisateur a tapé une adresse e-mail ou un pseudo (login)
        $identifier = trim($request->login);
        $member = Member::with('user')
            ->where('member_number', $identifier)
            ->orWhere('current_member_number', $identifier)
            ->first();

        // Les comptes techniques conservent leur identifiant interne.
        $user = $member?->user ?: User::where('login', $identifier)
            ->whereIn('role', ['admin', 'scanner'])
            ->first();

        // 3. Tentative de connexion
        // Auth::attempt va chercher l'utilisateur et vérifier que le mot de passe correspond au hash en BDD
        $remember = $request->boolean('rememberMe'); // Si l'utilisateur a coché "Se souvenir de moi"

        if ($user && Hash::check($request->password, $user->password)) {
            Auth::login($user, $remember);

            $emailVerificationPending = $user->role === 'member'
                && ! $user->email_verified_at
                && DB::table('email_verification_otps')->where('email', $user->email)->exists();

            if ($emailVerificationPending) {
                Auth::logout();

                return response()->json([
                    'success' => false,
                    'code' => 'email_verification_required',
                    'email' => $user->email,
                    'message' => 'Veuillez vérifier votre adresse e-mail avant de vous connecter.',
                ], 403);
            }
            
            // 4. Génération du Token de sécurité
            $token = $user->createToken('auth_token')->plainTextToken;

            // 5. On renvoie les infos exactes qu'Angular attend !
            return response()->json([
                'success' => true,
                'user' => $user, // Contient le 'role' pour la redirection Angular
                'token' => $token
            ]);
        }

        // Si échec de la connexion
        return response()->json([
            'success' => false,
            'message' => 'Numéro de carte membre ou mot de passe incorrect.'
        ], 401); // 401 = Unauthorized
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            $temporaryPassword = Str::password(12, symbols: false);
            DB::transaction(function () use ($user, $temporaryPassword) {
                $user->forceFill(['password' => Hash::make($temporaryPassword)])->save();
                Mail::to($user->email)->send(new TemporaryPasswordMail($user, $temporaryPassword));
                $user->tokens()->delete();
            });
        }

        return response()->json([
            'message' => 'Si cette adresse est associée à un compte, un mot de passe temporaire vient d’être envoyé.',
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

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return response()->json(['message' => 'Votre mot de passe a été modifié.']);
    }
}
