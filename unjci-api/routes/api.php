<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\AuthController; // <-- N'oublie pas l'import !
use App\Http\Controllers\AdminController;

Route::post('/members/apply', [MemberController::class, 'store']);
// Nouvelle route pour la connexion
Route::post('/login', [AuthController::class, 'login']);

// Routes privées (nécessitent d'être connecté)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/member/profile', [MemberController::class, 'profile']);
    Route::post('/member/payment', [MemberController::class, 'submitPayment']); // <-- Nouvelle route

    // Routes de l'Administration
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::put('/admin/members/{id}/status', [AdminController::class, 'updateMemberStatus']);
    Route::put('/admin/payments/{id}/validate', [AdminController::class, 'validatePayment']);

});