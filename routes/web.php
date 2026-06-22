<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SchoolController as AdminSchoolController;
use App\Http\Controllers\Admin\CardOrderController as AdminCardOrderController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\FeatureController as AdminFeatureController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\School\StudentImportController as SchoolStudentImportController;
use App\Http\Controllers\School\DashboardController as SchoolDashboardController;
use App\Http\Controllers\School\StudentController as SchoolStudentController;
use App\Http\Controllers\School\ClassController as SchoolClassController;
use App\Http\Controllers\School\SubjectController as SchoolSubjectController;
use App\Http\Controllers\School\StaffController as SchoolStaffController;
use App\Http\Controllers\School\AttendanceController as SchoolAttendanceController;
use App\Http\Controllers\School\GradeController as SchoolGradeController;
use App\Http\Controllers\School\CardOrderController as SchoolCardOrderController;
use App\Http\Controllers\School\PhotoSessionController as SchoolPhotoSessionController;
use App\Http\Controllers\School\GuardianController as SchoolGuardianController;
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

        // Leads
        Route::prefix('leads')->name('admin.leads.')->group(function () {
            Route::get('/',         [AdminLeadController::class, 'index'])->name('index');
            Route::get('/{lead}',   [AdminLeadController::class, 'show'])->name('show');
            Route::patch('/{lead}', [AdminLeadController::class, 'update'])->name('update');
        });

        // Audit Log
        Route::get('/audit-log', [AdminAuditLogController::class, 'index'])->name('admin.audit-log.index');

        // Features
        Route::prefix('features')->name('admin.features.')->group(function () {
            Route::get('/',                                          [AdminFeatureController::class, 'index'])->name('index');
            Route::post('/schools/{school}/overrides',               [AdminFeatureController::class, 'setOverride'])->name('override');
            Route::delete('/schools/{school}/overrides/{key}',       [AdminFeatureController::class, 'removeOverride'])->name('override.remove');
        });

        // Payments
        Route::prefix('payments')->name('admin.payments.')->group(function () {
            Route::get('/',                       [AdminPaymentController::class, 'index'])->name('index');
            Route::get('/create',                 [AdminPaymentController::class, 'create'])->name('create');
            Route::post('/',                      [AdminPaymentController::class, 'store'])->name('store');
            Route::get('/{payment}',              [AdminPaymentController::class, 'show'])->name('show');
            Route::post('/{payment}/confirm',     [AdminPaymentController::class, 'confirm'])->name('confirm');
        });

        // Card order fulfilment (enter code → confirm → printing → shipped → delivered)
        Route::prefix('card-orders')->name('admin.card-orders.')->group(function () {
            Route::get('/',                  [AdminCardOrderController::class, 'index'])->name('index');
            Route::get('/process',           [AdminCardOrderController::class, 'process'])->name('process');
            Route::get('/{cardOrder}',       [AdminCardOrderController::class, 'show'])->name('show');
            Route::post('/{cardOrder}/confirm', [AdminCardOrderController::class, 'confirm'])->name('confirm');
            Route::post('/{cardOrder}/status',  [AdminCardOrderController::class, 'updateStatus'])->name('status');
            Route::post('/{cardOrder}/cancel',  [AdminCardOrderController::class, 'cancel'])->name('cancel');
        });
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
        Route::resource('/attendance', SchoolAttendanceController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->names('school.attendance');

        // Grades (non-resource: class×subject×term×assessment_type)
        Route::prefix('grades')->name('school.grades.')->group(function () {
            Route::get('/',          [SchoolGradeController::class, 'index'])->name('index');
            Route::get('/enter',     [SchoolGradeController::class, 'enter'])->name('enter');
            Route::post('/save',     [SchoolGradeController::class, 'save'])->name('save');
            Route::post('/publish',  [SchoolGradeController::class, 'publish'])->name('publish');
            Route::get('/gradebook', [SchoolGradeController::class, 'gradebook'])->name('gradebook');
            Route::get('/terms',     [SchoolGradeController::class, 'terms'])->name('terms');
            Route::post('/terms',    [SchoolGradeController::class, 'termStore'])->name('terms.store');
        });

        // Guardians (nested under a student)
        Route::get('/guardians/search', [SchoolGuardianController::class, 'search'])->name('school.guardians.search');
        Route::prefix('students/{student}/guardians')->name('school.guardians.')->group(function () {
            Route::post('/',            [SchoolGuardianController::class, 'store'])->name('store');
            Route::patch('/{guardian}', [SchoolGuardianController::class, 'update'])->name('update');
            Route::delete('/{guardian}', [SchoolGuardianController::class, 'destroy'])->name('destroy');
        });

        // Photo Sessions
        Route::prefix('photo-sessions')->name('school.photo-sessions.')->group(function () {
            Route::get('/',                               [SchoolPhotoSessionController::class, 'index'])->name('index');
            Route::get('/create',                         [SchoolPhotoSessionController::class, 'create'])->name('create');
            Route::post('/',                              [SchoolPhotoSessionController::class, 'store'])->name('store');
            Route::get('/{photoSession}',                 [SchoolPhotoSessionController::class, 'show'])->name('show');
            Route::post('/{photoSession}/upload-photo',   [SchoolPhotoSessionController::class, 'uploadPhoto'])->name('upload-photo');
            Route::post('/{photoSession}/skip',           [SchoolPhotoSessionController::class, 'skipStudent'])->name('skip');
            Route::post('/{photoSession}/upload-zip',     [SchoolPhotoSessionController::class, 'uploadZip'])->name('upload-zip');
            Route::post('/{photoSession}/complete',       [SchoolPhotoSessionController::class, 'complete'])->name('complete');
            Route::post('/{photoSession}/cancel',         [SchoolPhotoSessionController::class, 'cancel'])->name('cancel');
        });

        // CSV Student Import
        Route::prefix('students/import')->name('school.students.import.')->group(function () {
            Route::get('/',           [SchoolStudentImportController::class, 'create'])->name('form');
            Route::post('/',          [SchoolStudentImportController::class, 'preview'])->name('preview');
            Route::post('/confirm',   [SchoolStudentImportController::class, 'confirm'])->name('confirm');
            Route::get('/template',   [SchoolStudentImportController::class, 'template'])->name('template');
        });

        // Card Orders (school side: select students → confirmation code → track)
        Route::prefix('card-orders')->name('school.card-orders.')->group(function () {
            Route::get('/',                   [SchoolCardOrderController::class, 'index'])->name('index');
            Route::get('/create',             [SchoolCardOrderController::class, 'create'])->name('create');
            Route::post('/',                  [SchoolCardOrderController::class, 'store'])->name('store');
            Route::get('/{cardOrder}',        [SchoolCardOrderController::class, 'show'])->name('show');
            Route::post('/{cardOrder}/cancel', [SchoolCardOrderController::class, 'cancel'])->name('cancel');
        });
    });

});