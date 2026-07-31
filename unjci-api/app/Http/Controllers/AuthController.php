<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'login';
        
        $credentials = [
            $loginField => $request->login,
            'password' => $request->password
        ];

        // 3. Tentative de connexion
        // Auth::attempt va chercher l'utilisateur et vérifier que le mot de passe correspond au hash en BDD
        $remember = $request->boolean('rememberMe'); // Si l'utilisateur a coché "Se souvenir de moi"

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();
            
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
            'message' => 'Login ou mot de passe incorrect.'
        ], 401); // 401 = Unauthorized
    }
}