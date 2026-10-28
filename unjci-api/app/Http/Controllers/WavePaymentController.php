<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Client\ConnectionException;
use App\Services\PaymentApproval;
use App\Mail\ApplicationUnderReviewMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

class WavePaymentController extends Controller
{
    public function initiateCheckout(Request $request)
    {
        $apiKey = config('services.wave.api_key');
        $signingSecret = config('services.wave.signing_secret');
        abort_unless(filled($apiKey), 503, 'Le paiement Wave est momentanément indisponible.');
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
            // Au lieu de bloquer, on annule les anciens paiements en attente
            // pour permettre à l'utilisateur de réessayer (ex: solde insuffisant sur Wave)
            $member->payments()->where('status', 'pending')->update(['status' => 'rejected']);
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
            'payment_phone' => 'Wave', // Placeholder car la colonne n'est pas nullable
            'transaction_id' => 'INIT_'.uniqid(), // ID temporaire remplacé lors du webhook
            'payment_type' => $validated['paymentType'],
            'previous_member_number' => $isRenewal ? $memberNumber : null,
            'status' => 'pending',
        ]);

        $frontendUrl = config('app.frontend_url', 'http://localhost:4200');
        $successUrl = rtrim($frontendUrl, '/') . '/espace-membre?payment=success&payment_id=' . $payment->id;
        $cancelUrl = rtrim($frontendUrl, '/') . '/espace-membre?payment=cancel';
        
        $payload = [
            'amount' => (string) $amount,
            'currency' => 'XOF',
            'error_url' => $cancelUrl,
            'success_url' => $successUrl,
            'client_reference' => (string) $payment->id,
        ];
        
        $timestamp = time();
        $body = json_encode($payload);
        $client = Http::withToken($apiKey)->connectTimeout(5)->timeout(15);
        if (filled($signingSecret)) {
            $signature = hash_hmac('sha256', $timestamp . $body, $signingSecret);
            $client = $client->withHeaders(['Wave-Signature' => "t={$timestamp},v1={$signature}"]);
        }
        try {
            // Envoyer exactement les octets signés, sans réencoder le JSON.
            $response = $client->withBody($body, 'application/json')
                ->post('https://api.wave.com/v1/checkout/sessions');
        } catch (ConnectionException) {
            // La requête peut avoir été reçue : conserver le paiement pour réconciliation.
            return response()->json(['message' => 'Wave ne répond pas. Vérifiez votre historique avant de réessayer.'], 503);
        }

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
        ]);

        return response()->json([
            'message' => 'Erreur lors de l\'initialisation du paiement avec Wave. Veuillez réessayer plus tard.'
        ], 500);
    }

    public function webhook(Request $request)
    {
        $webhookSecret = config('services.wave.webhook_secret');
        abort_unless(filled($webhookSecret), 503, 'Webhook indisponible.');
        $parts = explode(',', (string) $request->header('Wave-Signature'));
        $timestamp = null;
        $signatures = [];
        foreach ($parts as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        abort_unless($timestamp && (int) $timestamp >= now()->timestamp - 300 && (int) $timestamp <= now()->timestamp + 30, 401);
        $expected = hash_hmac('sha256', $timestamp.$request->getContent(), $webhookSecret);
        abort_unless(collect($signatures)->contains(fn ($signature) => hash_equals($expected, $signature)), 401);
        if ($request->input('type') !== 'checkout.session.completed') {
            return response()->json(['status' => 'ignored']);
        }
        $data = $request->validate([
            'data.client_reference' => 'required|integer',
            'data.id' => 'required|string',
            'data.transaction_id' => 'required|string|max:255',
            'data.currency' => 'required|in:XOF',
            'data.amount' => ['required', 'regex:/^\d+(?:\.0{1,2})?$/'],
            'data.checkout_status' => 'required|in:complete',
            'data.payment_status' => 'required|in:succeeded',
        ])['data'];
        $reviewMember = DB::transaction(function () use ($data) {
            $payment = Payment::lockForUpdate()->findOrFail($data['client_reference']);
            abort_unless($payment->checkout_session_id === $data['id'] && (int) $payment->amount === (int) $data['amount'], 422);
            if ($payment->status === 'approved') {
                abort_unless($payment->transaction_id === $data['transaction_id'], 422);
                return;
            }
            $payment->update(['status' => 'approved', 'transaction_id' => $data['transaction_id']]);
            return app(PaymentApproval::class)->apply($payment);
        }, 3);
        if ($reviewMember) {
            try {
                Mail::to($reviewMember->personal_email)->send(new ApplicationUnderReviewMail($reviewMember));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return response()->json(['status' => 'success']);
    }
}
