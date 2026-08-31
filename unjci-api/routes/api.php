<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminPressMediaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemberController; // <-- N'oublie pas l'import !
use App\Http\Controllers\PressMediaController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'application' => 'UNJCI API',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/press-media', [PressMediaController::class, 'index']);
Route::post('/contact', [ContactController::class, 'store']);

Route::get('/members/by-card/{cardNumber}', [MemberController::class, 'findByCardNumber']);
Route::post('/members/apply', [MemberController::class, 'store']);
Route::post('/members/email-verification/send', [MemberController::class, 'sendEmailVerificationOtp'])
    ->middleware('throttle:3,1');
Route::post('/members/email-verification/verify', [MemberController::class, 'verifyEmailOtp'])
    ->middleware('throttle:6,1');
// Nouvelle route pour la connexion
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');
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
    Route::post('/member/upload-old-cards', [MemberController::class, 'uploadOldCards']);

    // Routes de l'Administration
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/admin/admins', [AdminController::class, 'listAdmins']);
    Route::post('/admin/admins', [AdminController::class, 'storeAdmin']);
    Route::put('/admin/admins/{id}', [AdminController::class, 'updateAdmin']);
    Route::delete('/admin/admins/{id}', [AdminController::class, 'deleteAdmin']);
    Route::put('/admin/admins/{id}/password', [AdminController::class, 'resetAdminPassword']);
    Route::get('/admin/login-audits', [AdminController::class, 'loginAudits']);
    Route::put('/admin/members/{id}/status', [AdminController::class, 'updateMemberStatus']);
    Route::put('/admin/members/{id}/details', [AdminController::class, 'updateMemberDetails']);
    Route::put('/admin/payments/{id}/validate', [AdminController::class, 'validatePayment']);
    Route::get('/admin/press-companies', [AdminPressMediaController::class, 'index']);
    Route::post('/admin/press-companies', [AdminPressMediaController::class, 'storeCompany']);
    Route::put('/admin/press-companies/{company}', [AdminPressMediaController::class, 'updateCompany']);
    Route::delete('/admin/press-companies/{company}', [AdminPressMediaController::class, 'destroyCompany']);
    Route::post('/admin/press-companies/{company}/media', [AdminPressMediaController::class, 'storeMedia']);
    Route::put('/admin/press-media/{media}', [AdminPressMediaController::class, 'updateMedia']);
    Route::delete('/admin/press-media/{media}', [AdminPressMediaController::class, 'destroyMedia']);
    Route::get('/admin/contacts', [ContactController::class, 'index']);
    Route::get('/verify-card/{token}', [AdminController::class, 'verifyCard']);

});
