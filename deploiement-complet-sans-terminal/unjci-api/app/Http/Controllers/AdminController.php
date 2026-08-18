<?php

namespace App\Http\Controllers;

use App\Mail\ApplicationUnderReviewMail;
use App\Mail\MembershipApprovedMail;
use App\Models\LoginAudit;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\MemberNumberAllocator;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminController extends Controller
{
    public function __construct(private readonly MemberNumberAllocator $memberNumbers) {}

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

        $members = Member::with('payments')->orderBy('created_at', 'desc')->get();

        // On récupère TOUS les paiements (du plus récent au plus ancien)
        $payments = Payment::with('member')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'members' => $members,
            'payments' => $payments, // On renvoie tout à Angular
        ]);
    }

    public function loginAudits(Request $request)
    {
        $this->checkAdmin($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['all', 'success', 'failure'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? 'all';
        $perPage = (int) ($validated['perPage'] ?? 25);

        $audits = LoginAudit::query()
            ->with('user:id,name,email,role')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('login', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== 'all', fn ($query) => $query->where('success', $status === 'success'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $audits->getCollection()->map(fn (LoginAudit $audit) => [
                'id' => $audit->id,
                'login' => $audit->login,
                'userId' => $audit->user_id,
                'userName' => $audit->user?->name,
                'userEmail' => $audit->user?->email,
                'role' => $audit->user?->role,
                'success' => $audit->success,
                'reason' => $audit->failure_reason,
                'ipAddress' => $audit->ip_address,
                'userAgent' => $audit->user_agent,
                'createdAt' => $audit->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'currentPage' => $audits->currentPage(),
                'lastPage' => $audits->lastPage(),
                'perPage' => $audits->perPage(),
                'total' => $audits->total(),
            ],
        ]);
    }

    public function updateMemberStatus(Request $request, $id)
    {
        $this->checkAdmin($request);

        if (is_string($request->input('verifiedMemberNumber'))) {
            $request->merge([
                'verifiedMemberNumber' => strtoupper(trim($request->input('verifiedMemberNumber'))),
            ]);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'verifiedMemberNumber' => ['nullable', 'string', 'regex:/^UJ\d{2}-\d{5}$/'],
        ], [
            'verifiedMemberNumber.regex' => 'Le numéro de membre UNJCI doit respecter le format UJ25-00122.',
        ]);

        $targetStatus = $validated['status'];
        $verifiedMemberNumber = $validated['verifiedMemberNumber'] ?? null;
        $sendApprovalEmail = false;

        try {
            $member = DB::transaction(function () use ($id, $targetStatus, $verifiedMemberNumber, &$sendApprovalEmail) {
                $member = Member::with('user')->lockForUpdate()->findOrFail($id);

                if ($member->status === $targetStatus) {
                    return $member;
                }

                if ($targetStatus === 'pending') {
                    $member->update(['status' => 'pending', 'approved_at' => null]);

                    return $member;
                }

                if ($targetStatus === 'rejected') {
                    if (! $member->member_number && ! $member->application_submitted_at) {
                        throw ValidationException::withMessages([
                            'status' => ['Le paiement doit être confirmé avant d’examiner cette demande.'],
                        ]);
                    }

                    $member->update([
                        'status' => 'rejected',
                        'approved_at' => null,
                        // Une proposition refusée redevient disponible pour un
                        // autre dossier. Les numéros officiels sont conservés.
                        'current_member_number' => $member->member_number ?: null,
                    ]);

                    return $member;
                }

                $paymentType = $member->request_type === 'renewal' ? 'renewal' : 'adhesion';
                $hasConfirmedPayment = $member->payments()
                    ->where('payment_type', $paymentType)
                    ->where('status', 'approved')
                    ->exists();

                if (! $member->member_number && (! $member->application_submitted_at || ! $hasConfirmedPayment)) {
                    throw ValidationException::withMessages([
                        'status' => [sprintf(
                            'Le paiement de %s doit être confirmé avant l’approbation du dossier.',
                            $paymentType === 'renewal' ? 'renouvellement' : 'adhésion',
                        )],
                    ]);
                }

                if ($member->member_number) {
                    $memberNumber = $member->member_number;
                } elseif ($member->request_type === 'renewal') {
                    // L'administrateur doit renvoyer explicitement le numéro qu'il
                    // a contrôlé. Une simple approbation ne suffit pas à promouvoir
                    // automatiquement la proposition enregistrée par le demandeur.
                    $memberNumber = $verifiedMemberNumber;
                    if (! $memberNumber) {
                        throw ValidationException::withMessages([
                            'verifiedMemberNumber' => ['Renseignez le numéro UNJCI vérifié avant d’approuver ce renouvellement.'],
                        ]);
                    }
                    $this->ensureMemberNumberIsAvailableFor($memberNumber, $member);
                } else {
                    $memberNumber = $this->memberNumbers->next();
                }

                $expiryBase = $member->membership_expires_at && $member->membership_expires_at->isFuture()
                    ? $member->membership_expires_at
                    : Carbon::today();

                $member->update([
                    'status' => 'approved',
                    'member_number' => $memberNumber,
                    'current_member_number' => $memberNumber,
                    'qr_token' => $member->qr_token ?: Str::random(40),
                    'membership_expires_at' => $expiryBase->copy()->addYear(),
                    'approved_at' => now(),
                ]);
                $member->user?->update(['login' => $memberNumber]);
                $sendApprovalEmail = true;

                return $member;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'verifiedMemberNumber' => ['Ce numéro UNJCI vient d’être attribué à un autre membre.'],
            ]);
        }

        if ($sendApprovalEmail) {
            $this->sendMailSafely($member, new MembershipApprovedMail($member->fresh()));
        }

        return response()->json(['success' => true, 'member' => $member->fresh()]);
    }

    public function validatePayment(Request $request, $id)
    {
        $this->checkAdmin($request);

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $targetStatus = $validated['status'];
        $sendUnderReviewEmail = false;
        $member = null;

        $payment = DB::transaction(function () use ($id, $targetStatus, &$sendUnderReviewEmail, &$member) {
            $payment = Payment::lockForUpdate()->findOrFail($id);

            if ($payment->status !== 'pending') {
                if ($payment->status === $targetStatus) {
                    $member = $payment->member;

                    return $payment;
                }

                throw ValidationException::withMessages([
                    'status' => ['Un paiement déjà traité ne peut plus changer de statut.'],
                ]);
            }

            $member = Member::lockForUpdate()->findOrFail($payment->member_id);
            $payment->update(['status' => $targetStatus]);

            if ($targetStatus !== 'approved') {
                return $payment;
            }

            if ($payment->payment_type === 'renewal' && $member->member_number) {
                $memberNumber = $member->member_number;
                $expiryBase = $member->membership_expires_at && $member->membership_expires_at->isFuture()
                    ? $member->membership_expires_at
                    : Carbon::today();
                $member->update([
                    'status' => 'approved',
                    'member_number' => $memberNumber,
                    'current_member_number' => $memberNumber,
                    'qr_token' => $member->qr_token ?: Str::random(40),
                    'membership_expires_at' => $expiryBase->copy()->addYear(),
                    'approved_at' => $member->approved_at ?: now(),
                ]);

                return $payment;
            }

            if (! $member->application_submitted_at) {
                $member->update([
                    'status' => 'pending',
                    'application_submitted_at' => now(),
                ]);
                $sendUnderReviewEmail = true;
            }

            return $payment;
        }, 3);

        if ($sendUnderReviewEmail && $member) {
            $this->sendMailSafely($member, new ApplicationUnderReviewMail($member->fresh()));
        }

        return response()->json([
            'success' => true,
            'payment' => $payment->fresh('member'),
        ]);
    }

    private function ensureMemberNumberIsAvailableFor(string $memberNumber, Member $member): void
    {
        $usedByMember = Member::query()
            ->where('id', '!=', $member->id)
            ->where(function ($query) use ($memberNumber) {
                $query->where('member_number', $memberNumber)
                    ->orWhere('current_member_number', $memberNumber);
            })
            ->lockForUpdate()
            ->exists();

        $usedByUser = User::query()
            ->where('id', '!=', $member->user_id)
            ->where('login', $memberNumber)
            ->lockForUpdate()
            ->exists();

        if ($usedByMember || $usedByUser) {
            throw ValidationException::withMessages([
                'verifiedMemberNumber' => ['Ce numéro de membre UNJCI est déjà utilisé.'],
            ]);
        }
    }

    private function sendMailSafely(Member $member, object $mailable): void
    {
        try {
            Mail::to($member->personal_email)->send($mailable);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function verifyCard(Request $request, $token)
    {
        $user = $request->user();

        // Seuls les admins et les scanners peuvent vérifier une carte
        if (! in_array($user->role, ['admin', 'scanner'])) {
            abort(403, 'Accès non autorisé.');
        }

        $member = Member::where('qr_token', $token)->firstOrFail();

        return response()->json([
            'success' => true,
            'member' => $member,
        ]);
    }
}
