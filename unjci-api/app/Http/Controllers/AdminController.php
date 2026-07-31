<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Payment;

class AdminController extends Controller
{
    // Petite fonction de sécurité pour bloquer les non-admins
    private function checkAdmin(Request $request) 
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Accès non autorisé.');
        }
    }

  public function dashboard(Request $request)
    {
        $this->checkAdmin($request);

        $members = Member::orderBy('created_at', 'desc')->get();

        // On récupère TOUS les paiements (du plus récent au plus ancien)
        $payments = Payment::with('member')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'members' => $members,
            'payments' => $payments // On renvoie tout à Angular
        ]);
    }

    public function updateMemberStatus(Request $request, $id)
    {
        $this->checkAdmin($request);

        $request->validate([
            'status' => 'required|in:pending,approved,rejected'
        ]);

        $member = Member::findOrFail($id);
        $member->update(['status' => $request->status]);

        return response()->json(['success' => true, 'member' => $member]);
    }

    public function validatePayment(Request $request, $id)
    {
        $this->checkAdmin($request);

        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        $payment = Payment::findOrFail($id);
        $payment->update(['status' => $request->status]);

        // BONUS UX : Si l'admin approuve le paiement, on active la carte du membre automatiquement
        if ($request->status === 'approved') {
            $member = $payment->member;
            
            // Si le membre était en attente, il devient actif
            if ($member && $member->status === 'pending') {
                $member->update([
                    'status' => 'ACTIVE',
                    // On pourrait aussi générer son member_number ici s'il n'en a pas encore !
                ]);
            }
        }

        return response()->json(['success' => true, 'payment' => $payment]);
    }
}