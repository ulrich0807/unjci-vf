<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Support\Str;
use Carbon\Carbon;

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
        
        // On prépare le tableau des modifications avec le nouveau statut
        $updateData = ['status' => $request->status];

        // Si on approuve le membre ET qu'il n'a pas encore de token
        if ($request->status === 'approved' && empty($member->qr_token)) {
            $updateData['qr_token'] = Str::random(40);
            
        }

        // Exécution de la mise à jour en base de données
        $member->update($updateData);

        return response()->json(['success' => true, 'member' => $member]);
    }

   public function validatePayment(Request $request, $id)
    {
        $this->checkAdmin($request);

        $request->validate([
            'status' => 'required|in:pending,approved,rejected'
        ]);

        $payment = Payment::findOrFail($id);

        if ($payment->status === 'approved' && $request->status === 'approved') {
            return response()->json(['success' => true, 'payment' => $payment]);
        }

        $payment->update(['status' => $request->status]);

        if ($request->status === 'approved') {
            $member = $payment->member;
            
            if ($member) {
                $expiryBase = $member->membership_expires_at
                    && $member->membership_expires_at->isFuture()
                        ? $member->membership_expires_at
                        : Carbon::today();

                $member->update([
                    'status' => 'approved',
                    'qr_token' => $member->qr_token ?: Str::random(40),
                    'member_number' => $member->member_number,
                    'current_member_number' => $member->member_number,
                    'membership_expires_at' => $expiryBase->copy()->addYear(),
                ]);
            }
        }

        return response()->json(['success' => true, 'payment' => $payment]);
    }

    public function verifyCard(Request $request, $token)
    {
        $user = $request->user();
        
        // Seuls les admins et les scanners peuvent vérifier une carte
        if (!in_array($user->role, ['admin', 'scanner'])) {
            abort(403, 'Accès non autorisé.');
        }

        $member = Member::where('qr_token', $token)->firstOrFail();

        return response()->json([
            'success' => true,
            'member' => $member
        ]);
    }
}
