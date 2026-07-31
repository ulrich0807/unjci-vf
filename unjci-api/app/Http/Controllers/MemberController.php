<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class MemberController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validation (identique à avant)
        $validated = $request->validate([
            'lastName' => 'required|string|max:255',
            'firstName' => 'required|string|max:255',
            'birthDate' => 'required|date',
            'birthPlace' => 'required|string|max:255',
            'postalAddress' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'personalEmail' => 'required|email|unique:users,email', // On vérifie l'email dans users
            
            'requestType' => 'required|string',
            'currentMemberNumber' => 'nullable|string',
            'professionalStatus' => 'required|string',
            'employers' => 'required|string',
            'functionTitle' => 'required|string',
            'pressCardNumber' => 'required|string',
            'pressCardExpiry' => 'required|date',
            
            'pressCardFile' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'cvFile' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
            'photoFile' => 'required|file|mimes:jpg,jpeg,png|max:2048',

            'login' => 'required|string|min:4|unique:users,login', // On vérifie le login dans users
            'password' => 'required|string|min:8',
            
            'signatureName' => 'required|string|max:255',
            'signatureDate' => 'required|date',
            'declarationAccepted' => 'required|boolean',
            'privacyAccepted' => 'required|boolean',
        ]);

        // 2. Traitement des fichiers
        $pressCardPath = $request->file('pressCardFile')->store('members/press_cards', 'public');
        $photoPath = $request->file('photoFile')->store('members/photos', 'public');
        $cvPath = $request->hasFile('cvFile') ? $request->file('cvFile')->store('members/cvs', 'public') : null;

        // 3. Transaction Base de données : Création de User puis de Member
        $member = DB::transaction(function () use ($validated, $pressCardPath, $cvPath, $photoPath) {
            
            // A. Création des identifiants (Table users)
            $user = User::create([
                'name' => $validated['firstName'] . ' ' . $validated['lastName'],
                'email' => $validated['personalEmail'],
                'login' => $validated['login'],
                'password' => Hash::make($validated['password']), // Hachage sécurisé
                'role' => 'member',
            ]);

            // B. Création du profil (Table members) lié à l'utilisateur
            return Member::create([
                'user_id' => $user->id, // L'identifiant généré juste au-dessus
                
                'last_name' => $validated['lastName'],
                'first_name' => $validated['firstName'],
                'birth_date' => $validated['birthDate'],
                'birth_place' => $validated['birthPlace'],
                'postal_address' => $validated['postalAddress'],
                'phone' => $validated['phone'],
                'personal_email' => $validated['personalEmail'],
                
                'request_type' => $validated['requestType'],
                'current_member_number' => $validated['currentMemberNumber'] ?? null,
                'professional_status' => $validated['professionalStatus'],
                'employers' => $validated['employers'],
                'function_title' => $validated['functionTitle'],
                'press_card_number' => $validated['pressCardNumber'],
                'press_card_expiry' => $validated['pressCardExpiry'],
                
                'press_card_file_path' => $pressCardPath,
                'cv_file_path' => $cvPath,
                'photo_file_path' => $photoPath,
                
                'signature_name' => $validated['signatureName'],
                'signature_date' => $validated['signatureDate'],
                'declaration_accepted' => $validated['declarationAccepted'],
                'privacy_accepted' => $validated['privacyAccepted'],
            ]);
        });

        // 4. Réponse
        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie.',
            'data' => $member
        ], 201);
    }

    // Modifie cette fonction existante
    public function profile(Request $request)
    {
        $user = $request->user();
        // On charge le membre AVEC ses paiements, triés du plus récent au plus ancien
        $member = $user->member()->with(['payments' => function($query) {
            $query->latest();
        }])->first();

        if (!$member) {
            return response()->json(['message' => 'Aucun dossier membre associé.'], 404);
        }

        return response()->json($member);
    }

    // Ajoute cette nouvelle fonction
    public function submitPayment(Request $request)
    {
        $request->validate([
            'paymentPhone' => 'required|string',
            'transactionId' => 'required|string',
            'amount' => 'required|numeric'
        ]);

        $member = $request->user()->member;

        if (!$member) {
            return response()->json(['message' => 'Membre introuvable'], 404);
        }

        $payment = $member->payments()->create([
            'amount' => $request->amount,
            'payment_phone' => $request->paymentPhone,
            'transaction_id' => $request->transactionId,
            'status' => 'pending' // En attente de validation par l'admin
        ]);

        return response()->json([
            'success' => true,
            'payment' => $payment
        ]);
    }
}