<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\ScheduleController;

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

        // Schema drift (deploy skipped `migrate`) has caused production 500s —
        // surface it here so uptime monitors flag it before users hit it.
        $pending = \App\Support\SchemaHealth::pendingMigrations();

        return response()->json([
            'status'  => ($dbStatus === 'ok' && $storageStatus === 'ok' && $pending === 0) ? 'ok' : 'degraded',
            'db'      => $dbStatus,
            'storage' => $storageStatus,
            'pending_migrations' => $pending,
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

    // PIN auth (quick app unlock)
    Route::post('/auth/pin/set', [AuthController::class, 'setPin'])->name('api.pin.set');
    Route::post('/auth/pin/verify', [AuthController::class, 'verifyPin'])->name('api.pin.verify');

    // ── Mobile scanner / attendance (Flutter app) ────────────────
    // Classes + roster the app caches for offline use.
    Route::get('/classes', [ClassController::class, 'index'])->name('api.classes.index');
    Route::get('/classes/{class}/students', [ClassController::class, 'students'])->name('api.classes.students');
    Route::get('/classes/{class}/students/{student}/photo', [ClassController::class, 'studentPhoto'])->name('api.classes.student-photo');

    // Grades for a class (teacher view)
    Route::get('/classes/{class}/grades', [GradeController::class, 'grades'])->name('api.classes.grades');

    // Schedule / timetable
    Route::get('/schedule/today', [ScheduleController::class, 'today'])->name('api.schedule.today');
    Route::get('/schedule/week', [ScheduleController::class, 'week'])->name('api.schedule.week');

    // Online QR verification + offline attendance sync.
    Route::post('/attendance/verify-qr', [AttendanceController::class, 'verifyQr'])->name('api.attendance.verify');
    Route::post('/attendance/sync', [AttendanceController::class, 'sync'])->name('api.attendance.sync');

});
