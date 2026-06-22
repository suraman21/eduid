<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SchoolController as AdminSchoolController;
use App\Http\Controllers\School\DashboardController as SchoolDashboardController;
use App\Http\Controllers\School\StudentController as SchoolStudentController;
use App\Http\Controllers\School\ClassController as SchoolClassController;
use App\Http\Controllers\School\SubjectController as SchoolSubjectController;
use App\Http\Controllers\School\StaffController as SchoolStaffController;
use App\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes — EduID SaaS Platform
|--------------------------------------------------------------------------
|
| Route Architecture:
|   /              → Public marketing & sales page
|   /contact       → Lead capture form (POST)
|   /login         → School portal login
|   /admin/login   → Super Admin login
|   /school/*      → School dashboard (auth required)
|   /admin/*       → Super Admin dashboard (auth required)
|
*/

// ── Public Routes ─────────────────────────────────────────────

// Marketing / Sales page
Route::get('/', function () {
    // If already logged in, send them to their dashboard
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            return redirect('/admin/dashboard');
        }
        return redirect('/school/dashboard');
    }
    return view('welcome');
})->name('home');

// Lead capture / Contact form
Route::post('/contact', [LeadController::class, 'store'])->name('contact.store');

// ── Auth Routes ───────────────────────────────────────────────

// School User Login (School Code + Email + Password)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

// Super Admin Login (Email + Password only)
Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'login']);

// Redirect /admin to /admin/login (or dashboard if already logged in)
Route::get('/admin', function () {
    if (Auth::check() && Auth::user()->isSuperAdmin()) {
        return redirect('/admin/dashboard');
    }
    return redirect()->route('admin.login');
});

// Logout (POST for CSRF protection)
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ── Protected Routes ──────────────────────────────────────────

Route::middleware(['auth'])->group(function () {

    // Super Admin Group
    Route::middleware(['role:super_admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::resource('/schools', AdminSchoolController::class)->names([
            'index'   => 'admin.schools.index',
            'create'  => 'admin.schools.create',
            'store'   => 'admin.schools.store',
            'show'    => 'admin.schools.show',
            'edit'    => 'admin.schools.edit',
            'update'  => 'admin.schools.update',
            'destroy' => 'admin.schools.destroy',
        ]);
    });

    // School User Group
    Route::middleware(['role:school_admin,teacher,staff', 'school.scope'])->prefix('school')->group(function () {
        Route::get('/dashboard', [SchoolDashboardController::class, 'index'])->name('school.dashboard');
        // Resource routes are registered for the methods the controllers
        // actually implement. Write actions are additionally guarded
        // server-side (school_admin only) inside each controller.
        Route::resource('/students', SchoolStudentController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
            ->names('school.students');
        Route::resource('/classes', SchoolClassController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->names('school.classes');
        Route::resource('/subjects', SchoolSubjectController::class)
            ->only(['index', 'store', 'update'])
            ->names('school.subjects');
        Route::resource('/staff', SchoolStaffController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
            ->names('school.staff');
    });

});