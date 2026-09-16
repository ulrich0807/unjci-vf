<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WavePaymentController extends Controller
{
    public function initiateCheckout(Request $request)
    {
        $validated = $request->validate([
            'paymentType' => 'required|in:adhesion,renewal',
        ]);

        $user = $request->user();
        $member = $user->member;

        if (!$member) {
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
        
        // Pour un renouvellement, on s'assure que c'est possible
        if ($isRenewal && empty($member->old_card_recto_path) && empty($member->previous_member_number) && empty($member->member_number)) {
            // L'utilisateur doit être un ancien membre pour faire un renouvellement
             return response()->json(['message' => 'L\'ancienne carte ou un numéro de membre est requis pour un renouvellement.'], 422);
        }

        $amount = $isRenewal ? 5000 : 10000;

        // On crée un enregistrement de paiement "pending"
        $payment = $member->payments()->create([
            'amount' => $amount,
            'payment_phone' => null, // Remplacé par l'API Wave
            'transaction_id' => 'INIT_'.uniqid(), // ID temporaire remplacé lors du webhook
            'payment_type' => $validated['paymentType'],
            'previous_member_number' => $isRenewal ? $memberNumber : null,
            'status' => 'pending',
        ]);

        $apiKey = config('services.wave.api_key');
        
        $frontendUrl = config('app.frontend_url', 'http://localhost:4200');
        $successUrl = $frontendUrl . '/member-dashboard?payment=success&payment_id=' . $payment->id;
        $cancelUrl = $frontendUrl . '/member-dashboard?payment=cancel';

        // Appel de l'API Wave selon la doc officielle (simplifiée ici)
        $response = Http::withToken($apiKey)->post('https://api.wave.com/v1/checkout/sessions', [
            'amount' => $amount,
            'currency' => 'XOF',
            'error_url' => $cancelUrl,
            'success_url' => $successUrl,
            'client_reference' => (string) $payment->id,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            $payment->update([
                'checkout_session_id' => $data['id']
            ]);

            return response()->json([
                'checkout_url' => $data['wave_launch_url'],
            ]);
        }

        $payment->delete();

        Log::error('Erreur API Wave Checkout', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return response()->json([
            'message' => 'Erreur lors de l\'initialisation du paiement avec Wave. Veuillez réessayer plus tard.'
        ], 500);
    }

    public function webhook(Request $request)
    {
        $webhookSecret = config('services.wave.webhook_secret');
        $signature = $request->header('wave-signature');

        // Validation de la signature Wave (Exemple simplifié)
        // Les détails exacts dépendent de la doc de l'API Wave. 
        // Généralement : hash_hmac('sha256', $request->getContent(), $webhookSecret)
        
        $payload = $request->all();
        
        if (isset($payload['type']) && $payload['type'] === 'checkout.session.completed') {
            $data = $payload['data'];
            $paymentId = $data['client_reference'] ?? null;
            $transactionId = $data['transaction_id'] ?? ($data['id'] ?? null); // Fallback

            if ($paymentId) {
                $payment = Payment::find($paymentId);
                
                if ($payment && $payment->status !== 'approved') {
                    $payment->update([
                        'status' => 'approved',
                        'transaction_id' => $transactionId
                    ]);

                    if ($payment->payment_type === 'adhesion' && !$payment->member->application_submitted_at) {
                        $payment->member->update([
                            'application_submitted_at' => now(),
                        ]);
                    }
                    
                    Log::info('Paiement Wave validé via webhook', ['payment_id' => $payment->id]);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}
