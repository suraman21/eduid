<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\AttendanceController;

/*
|--------------------------------------------------------------------------
| API Routes — EduID SaaS Platform
|--------------------------------------------------------------------------
|
| All API endpoints for the Flutter mobile app.
| Prefix: /api/v1/
| Auth: Laravel Sanctum token-based authentication.
|
*/

// ── Public Endpoints ─────────────────────────────────────────────
Route::prefix('v1')->group(function () {

    // Health check — used by uptime monitors
    Route::get('/health', function () {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $dbStatus = 'ok';
        } catch (\Exception $e) {
            $dbStatus = 'error';
        }

        $storageStatus = is_writable(storage_path('app')) ? 'ok' : 'error';

        return response()->json([
            'status'  => ($dbStatus === 'ok' && $storageStatus === 'ok') ? 'ok' : 'degraded',
            'db'      => $dbStatus,
            'storage' => $storageStatus,
            'version' => '1.0.0',
            'time'    => now()->toIso8601String(),
        ]);
    })->name('api.health');

    // Authentication Login
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.login');

});

// ── Protected Endpoints (Requires Sanctum Token) ─────────────────
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:mobile'])->group(function () {

    // Get current authenticated user details
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.me');

    // Authentication Logout
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.logout');

    // ── Mobile scanner / attendance (Flutter app) ────────────────
    // Classes + roster the app caches for offline use.
    Route::get('/classes', [ClassController::class, 'index'])->name('api.classes.index');
    Route::get('/classes/{class}/students', [ClassController::class, 'students'])->name('api.classes.students');

    // Online QR verification + offline attendance sync.
    Route::post('/attendance/verify-qr', [AttendanceController::class, 'verifyQr'])->name('api.attendance.verify');
    Route::post('/attendance/sync', [AttendanceController::class, 'sync'])->name('api.attendance.sync');

});
