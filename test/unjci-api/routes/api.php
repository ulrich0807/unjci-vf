<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\AuthController; // <-- N'oublie pas l'import !
use App\Http\Controllers\AdminController;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'application' => 'UNJCI API',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/members/check-email', [MemberController::class, 'checkEmailAvailability'])
    ->middleware('throttle:10,1');
Route::get('/members/by-card/{cardNumber}', [MemberController::class, 'findByCardNumber']);
Route::post('/members/apply', [MemberController::class, 'store']);
Route::post('/members/email-verification/send', [MemberController::class, 'sendEmailVerificationOtp'])
    ->middleware('throttle:3,1');
Route::post('/members/email-verification/verify', [MemberController::class, 'verifyEmailOtp'])
    ->middleware('throttle:6,1');
Route::post('/members/{member}/confirmation-email', [MemberController::class, 'sendApplicationConfirmation'])
    ->middleware('throttle:3,1');
// Nouvelle route pour la connexion
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:5,1');

// Permet de servir les fichiers du disque public sans lien symbolique serveur.
Route::get('/storage/{path}', function (string $path) {
    abort_unless(Storage::disk('public')->exists($path), 404);

    return response()->file(Storage::disk('public')->path($path));
})->where('path', '.*');

// Routes privées (nécessitent d'être connecté)
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/member/password', [AuthController::class, 'changePassword']);
    Route::get('/member/profile', [MemberController::class, 'profile']);
    Route::post('/member/payment', [MemberController::class, 'submitPayment']); // <-- Nouvelle route

    // Routes de l'Administration
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::put('/admin/members/{id}/status', [AdminController::class, 'updateMemberStatus']);
    Route::put('/admin/payments/{id}/validate', [AdminController::class, 'validatePayment']);
    Route::get('/verify-card/{token}', [AdminController::class, 'verifyCard']);

});
