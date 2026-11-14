<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SchoolController as AdminSchoolController;
use App\Http\Controllers\Admin\CardOrderController as AdminCardOrderController;
use App\Http\Controllers\Admin\SheetPrintController as AdminSheetPrintController;
use App\Http\Controllers\Admin\PrinterProfileController as AdminPrinterProfileController;
use App\Http\Controllers\Admin\CardTemplateController as AdminCardTemplateController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\FeatureController as AdminFeatureController;
use App\Http\Controllers\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\School\AnalyticsController as SchoolAnalyticsController;
use App\Http\Controllers\School\StudentImportController as SchoolStudentImportController;
use App\Http\Controllers\School\DashboardController as SchoolDashboardController;
use App\Http\Controllers\School\StudentController as SchoolStudentController;
use App\Http\Controllers\School\StudentLifecycleController as SchoolStudentLifecycleController;
use App\Http\Controllers\School\ProgrammeController as SchoolProgrammeController;
use App\Http\Controllers\School\BulkPromotionController as SchoolBulkPromotionController;
use App\Http\Controllers\School\AdmissionController as SchoolAdmissionController;
use App\Http\Controllers\School\ClassController as SchoolClassController;
use App\Http\Controllers\School\SubjectController as SchoolSubjectController;
use App\Http\Controllers\School\TimetableController as SchoolTimetableController;
use App\Http\Controllers\School\YearTransitionController as SchoolYearTransitionController;
use App\Http\Controllers\School\StaffController as SchoolStaffController;
use App\Http\Controllers\School\AttendanceController as SchoolAttendanceController;
use App\Http\Controllers\School\GradeController as SchoolGradeController;
use App\Http\Controllers\School\CardOrderController as SchoolCardOrderController;
use App\Http\Controllers\School\PhotoSessionController as SchoolPhotoSessionController;
use App\Http\Controllers\School\GuardianAccountController as SchoolGuardianAccountController;
use App\Http\Controllers\School\GuardianController as SchoolGuardianController;
use App\Http\Controllers\School\ReportCardController as SchoolReportCardController;
use App\Http\Controllers\School\IdCardController as SchoolIdCardController;
use App\Http\Controllers\School\FeeController as SchoolFeeController;
use App\Http\Controllers\School\SettingsController as SchoolSettingsController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

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

// Marketing / Sales page. (Controller, not a closure, so route:cache works.)
Route::get('/', [\App\Http\Controllers\SystemController::class, 'home'])->name('home');

// Lead capture / Contact form (public — throttled against lead-spam).
Route::post('/contact', [LeadController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

// Client-side error sink: the React error boundary posts unexpected UI crashes
// here so the details are logged on the SERVER (never shown to the user or the
// browser console). Throttled so a crash-loop can't flood the log.
Route::post('/client-error', [\App\Http\Controllers\SystemController::class, 'clientError'])
    ->middleware('throttle:contact')->name('client-error');

// ── Auth Routes ───────────────────────────────────────────────

// School User Login (School Code + Email + Password)
// PRD §08: max 5 login attempts per 15 minutes.
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');

// Super Admin Login (Email + Password, then emailed OTP — PRD §15 2FA)
Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'login'])->middleware('throttle:login');
Route::get('/admin/login/verify', [AdminLoginController::class, 'showOtpForm'])->name('admin.otp');
Route::post('/admin/login/verify', [AdminLoginController::class, 'verifyOtp'])->middleware('throttle:login')->name('admin.otp.verify');

// Redirect /admin to /admin/login (or dashboard if already logged in)
Route::get('/admin', [\App\Http\Controllers\SystemController::class, 'adminEntry']);

// Super Admin logout (admin guard only — a school login in the same browser
// survives; each portal signs out independently).
Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');

// Password Reset (OTP via email — school portal users only).
// Rate-limited to blunt enumeration + email-bombing.
Route::get('/forgot-password',  [PasswordResetController::class, 'showForgotForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp'])->middleware('throttle:login')->name('password.email');
Route::get('/reset-password',   [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password',  [PasswordResetController::class, 'reset'])->middleware('throttle:login')->name('password.update');

// Logout (POST for CSRF protection)
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ── Protected Routes ──────────────────────────────────────────

// Super Admin Group — OUTSIDE the web-guard group. `auth:admin,web`
// authenticates against the dedicated admin guard first (dual-session
// support) while still honouring a super-admin logged in via the web guard;
// role:super_admin then rejects any school user either way.
Route::middleware(['auth:admin,web', 'role:super_admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

        // Self-service profile (name / email / password)
        Route::get('/profile',           [ProfileController::class, 'show'])->name('admin.profile.show');
        Route::patch('/profile',         [ProfileController::class, 'update'])->name('admin.profile.update');
        Route::put('/profile/password',  [ProfileController::class, 'updatePassword'])->name('admin.profile.password');
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

        // Packages — plan tiers, plan×feature matrix, per-school package
        Route::prefix('packages')->name('admin.packages.')->group(function () {
            Route::get('/',                       [AdminPackageController::class, 'index'])->name('index');
            Route::patch('/plans/{plan}',         [AdminPackageController::class, 'updatePlan'])->name('plans.update');
            Route::post('/plan-features',         [AdminPackageController::class, 'togglePlanFeature'])->name('plan-features.toggle');
            Route::patch('/schools/{school}',     [AdminPackageController::class, 'updateSchoolPackage'])->name('schools.update');
        });

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

        // Card template editor (per-school designer background + field positions)
        Route::prefix('schools/{school}/card-template')->name('admin.card-template.')->group(function () {
            Route::get('/',             [AdminCardTemplateController::class, 'edit'])->name('edit');
            Route::post('/upload',      [AdminCardTemplateController::class, 'uploadImage'])->name('upload');
            Route::post('/save',        [AdminCardTemplateController::class, 'saveFields'])->name('save');
            Route::post('/convert',     [AdminCardTemplateController::class, 'convertLegacy'])->name('convert');
            Route::get('/image/{side}', [AdminCardTemplateController::class, 'serveImage'])->name('image');
        });
        // Generate print-ready cards (PDF or PNG ZIP) from a confirmed order + template
        Route::post('/card-orders/{cardOrder}/generate', [AdminCardTemplateController::class, 'generateCards'])->name('admin.card-orders.generate');

        // Card order fulfilment (enter code → confirm → printing → shipped → delivered)
        Route::prefix('card-orders')->name('admin.card-orders.')->group(function () {
            Route::get('/',                  [AdminCardOrderController::class, 'index'])->name('index');
            Route::get('/process',           [AdminCardOrderController::class, 'process'])->name('process');
            Route::get('/{cardOrder}',       [AdminCardOrderController::class, 'show'])->name('show');
            Route::post('/{cardOrder}/confirm', [AdminCardOrderController::class, 'confirm'])->name('confirm');
            Route::post('/{cardOrder}/status',  [AdminCardOrderController::class, 'updateStatus'])->name('status');
            Route::post('/{cardOrder}/cancel',  [AdminCardOrderController::class, 'cancel'])->name('cancel');
            // Print-ready PDF (TCPDF, server-side only, Super Admin only).
            Route::post('/{cardOrder}/print-file/regenerate', [AdminCardOrderController::class, 'regeneratePrintFile'])->name('print-file.regenerate');
            Route::get('/{cardOrder}/print-file',  [AdminCardOrderController::class, 'downloadPrintFile'])->name('print-file');
            // Client-side print view (browser Save-as-PDF) + its photo source.
            Route::get('/{cardOrder}/print', [AdminCardOrderController::class, 'printView'])->name('print');
            Route::get('/{cardOrder}/student-photo/{student}', [AdminCardOrderController::class, 'studentPhoto'])->name('student-photo');
            // Professional multi-up sheet printing wizard.
            Route::get('/{cardOrder}/sheet-print', [AdminSheetPrintController::class, 'wizard'])->name('sheet-print.wizard');
            Route::post('/{cardOrder}/sheet-print/generate', [AdminSheetPrintController::class, 'generate'])->name('sheet-print.generate');
        });

        // Sheet PDF download (most-recent front/back for a sheet).
        Route::get('/print-sheets/{printSheet}/download/{side}', [AdminSheetPrintController::class, 'download'])->name('admin.sheet-print.download');

        // Printer calibration profiles + calibration test sheets.
        Route::prefix('printers')->name('admin.printers.')->group(function () {
            Route::get('/',                     [AdminPrinterProfileController::class, 'index'])->name('index');
            Route::post('/',                    [AdminPrinterProfileController::class, 'store'])->name('store');
            Route::patch('/{printerProfile}',   [AdminPrinterProfileController::class, 'update'])->name('update');
            Route::delete('/{printerProfile}',  [AdminPrinterProfileController::class, 'destroy'])->name('destroy');
            Route::get('/{printerProfile}/calibration/{sheetTemplate}/{side}', [AdminPrinterProfileController::class, 'calibrationSheet'])->name('calibration');
        });
    });

// ── School User Groups — web guard, split by role ─────────────
//
// Three nested permission rings (defense in depth — controllers ALSO guard
// writes with requireAdmin(), but a teacher should never even reach an
// admin page's GET):
//   ring 1  all school roles   → dashboard, profile, attendance, grades
//   ring 2  admin only         → registry writes' UI, staff, orders, photos, import
//   ring 3  admin + staff      → student/class registry (read + admin-guarded writes)
//
// Registration ORDER matters: /students/import (ring 2) must beat the
// /students/{student} pattern (ring 3), so ring 2 is registered first.

// Ring 1 — every school role (school_admin, teacher, staff).
Route::middleware(['auth', 'role:school_admin,teacher,staff', 'school.scope'])->prefix('school')->group(function () {
        Route::get('/dashboard', [SchoolDashboardController::class, 'index'])->name('school.dashboard');

        // Self-service profile (name / email / password) — all school roles.
        Route::get('/profile',          [ProfileController::class, 'show'])->name('school.profile.show');
        Route::patch('/profile',        [ProfileController::class, 'update'])->name('school.profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('school.profile.password');

        // Onboarding tour UI state (welcome coach-marks + checklist).
        Route::patch('/onboarding', [OnboardingController::class, 'update'])->name('school.onboarding.update');

        // PRD §10: student photos are served through this authenticated,
        // tenant-scoped route — never via direct /storage URLs. Teachers need
        // them for attendance rosters; the controller scopes them to their
        // own classes.
        Route::get('/students/{student}/photo', [SchoolStudentController::class, 'photo'])
            ->name('school.students.photo');

        // Single-student ID card preview (teachers: own classes only —
        // enforced in the controller via requireTeacherCanAccessClass).
        Route::get('/students/{student}/id-card', [SchoolIdCardController::class, 'show'])
            ->name('school.students.id-card');
        // Serve the school's own card-template background (tenant-scoped, private disk)
        // so the preview works even when the public-disk copy is absent.
        Route::get('/id-card/template-image/{side}', [SchoolIdCardController::class, 'templateImage'])
            ->name('school.id-card.template-image');

        Route::resource('/attendance', SchoolAttendanceController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->names('school.attendance');

        // Grades (non-resource: class×subject×term×assessment_type).
        // Teachers are scoped to their assigned classes AND subject slots.
        // Gated on the 'gradebook' module of the school's package.
        Route::prefix('grades')->name('school.grades.')->middleware('feature:gradebook')->group(function () {
            Route::get('/',          [SchoolGradeController::class, 'index'])->name('index');
            Route::get('/enter',     [SchoolGradeController::class, 'enter'])->name('enter');
            Route::post('/save',     [SchoolGradeController::class, 'save'])->name('save');
            Route::post('/publish',  [SchoolGradeController::class, 'publish'])->name('publish');
            Route::get('/gradebook', [SchoolGradeController::class, 'gradebook'])->name('gradebook');
            Route::get('/terms',     [SchoolGradeController::class, 'terms'])->name('terms');
            Route::post('/terms',    [SchoolGradeController::class, 'termStore'])->name('terms.store');
        });
    });

// Ring 2 — school admins only.
Route::middleware(['auth', 'role:school_admin', 'school.scope'])->prefix('school')->group(function () {
        // CSV Student Import — registered before the students resource,
        // otherwise GET students/{student} captures "import" as a student id.
        // PRD §08: CSV import limited to 3 requests/hour. Gated on the
        // 'csv_import' module (Standard plan and above).
        Route::prefix('students/import')->name('school.students.import.')->middleware('feature:csv_import')->group(function () {
            Route::get('/',         [SchoolStudentImportController::class, 'create'])->name('form');
            Route::post('/',        [SchoolStudentImportController::class, 'preview'])->name('preview')->middleware('throttle:csv-import');
            Route::post('/confirm', [SchoolStudentImportController::class, 'confirm'])->name('confirm')->middleware('throttle:csv-import');
            Route::get('/template', [SchoolStudentImportController::class, 'template'])->name('template');
        });

        Route::resource('/subjects', SchoolSubjectController::class)
            ->only(['index', 'store', 'update'])
            ->names('school.subjects');

        // Analytics dashboard — 'analytics' module (Premium).
        Route::get('/analytics', [SchoolAnalyticsController::class, 'index'])
            ->middleware('feature:analytics')->name('school.analytics.index');

        // Academic Year Promotion wizard — 'year_transition' module.
        Route::middleware('feature:year_transition')->group(function () {
            Route::get('/year-transition',  [SchoolYearTransitionController::class, 'index'])->name('school.year-transition.index');
            Route::post('/year-transition', [SchoolYearTransitionController::class, 'store'])->name('school.year-transition.store');
        });

        // Timetable builder (periods + weekly grid) — 'timetable' module.
        Route::prefix('timetable')->name('school.timetable.')->middleware('feature:timetable')->group(function () {
            Route::get('/',                    [SchoolTimetableController::class, 'index'])->name('index');
            Route::patch('/working-days',      [SchoolTimetableController::class, 'updateWorkingDays'])->name('working-days.update');
            Route::post('/periods',            [SchoolTimetableController::class, 'storePeriod'])->name('periods.store');
            Route::patch('/periods/{period}',  [SchoolTimetableController::class, 'updatePeriod'])->name('periods.update');
            Route::delete('/periods/{period}', [SchoolTimetableController::class, 'destroyPeriod'])->name('periods.destroy');
            Route::post('/entries',            [SchoolTimetableController::class, 'saveEntry'])->name('entries.save');
            Route::delete('/entries',          [SchoolTimetableController::class, 'clearEntry'])->name('entries.clear');
            Route::post('/makeup',             [SchoolTimetableController::class, 'storeMakeup'])->name('makeup.store');
            Route::delete('/makeup/{makeup}',  [SchoolTimetableController::class, 'destroyMakeup'])->name('makeup.destroy');
        });

        // Student fee management — 'fees' module.
        Route::prefix('fees')->name('school.fees.')->middleware('feature:fees')->group(function () {
            Route::get('/',                              [SchoolFeeController::class, 'index'])->name('index');
            Route::post('/structures',                   [SchoolFeeController::class, 'storeStructure'])->name('structures.store');
            Route::patch('/structures/{structure}',      [SchoolFeeController::class, 'updateStructure'])->name('structures.update');
            Route::delete('/structures/{structure}',     [SchoolFeeController::class, 'destroyStructure'])->name('structures.destroy');
            Route::post('/structures/{structure}/generate', [SchoolFeeController::class, 'generateInvoices'])->name('structures.generate');
            Route::post('/invoices',                     [SchoolFeeController::class, 'storeInvoice'])->name('invoices.store');
            Route::patch('/invoices/{invoice}/waive',    [SchoolFeeController::class, 'waiveInvoice'])->name('invoices.waive');
            Route::delete('/invoices/{invoice}',         [SchoolFeeController::class, 'destroyInvoice'])->name('invoices.destroy');
            Route::get('/invoices/{invoice}/payments',   [SchoolFeeController::class, 'invoicePayments'])->name('invoices.payments');
            Route::post('/invoices/{invoice}/payments',  [SchoolFeeController::class, 'recordPayment'])->name('invoices.pay');
        });
        // School configuration (attendance cadence, branding, contact).
        Route::get('/settings',   [SchoolSettingsController::class, 'index'])->name('school.settings.index');
        Route::patch('/settings', [SchoolSettingsController::class, 'update'])->name('school.settings.update');

        Route::resource('/staff', SchoolStaffController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
            ->names('school.staff');

        // Report Cards (web-viewable, print-friendly — no server-side PDF).
        // Gated on the 'report_cards' module of the school's package.
        Route::middleware('feature:report_cards')->group(function () {
            Route::get('/report-cards',           [SchoolReportCardController::class, 'index'])->name('school.report-cards.index');
            Route::get('/report-cards/export',    [SchoolReportCardController::class, 'exportGradebook'])->name('school.report-cards.export');
            Route::get('/report-cards/{student}', [SchoolReportCardController::class, 'show'])->name('school.report-cards.show');
        });

        // Parent app logins for guardians — 'parent_app' module.
        Route::middleware('feature:parent_app')->group(function () {
            Route::post('/guardians/{guardian}/account',   [SchoolGuardianAccountController::class, 'store'])->name('school.guardians.account.store');
            Route::delete('/guardians/{guardian}/account', [SchoolGuardianAccountController::class, 'destroy'])->name('school.guardians.account.destroy');
        });

        // Guardians (nested under a student)
        Route::get('/guardians/search', [SchoolGuardianController::class, 'search'])->name('school.guardians.search');
        Route::prefix('students/{student}/guardians')->name('school.guardians.')->group(function () {
            Route::post('/',            [SchoolGuardianController::class, 'store'])->name('store');
            Route::patch('/{guardian}', [SchoolGuardianController::class, 'update'])->name('update');
            Route::delete('/{guardian}', [SchoolGuardianController::class, 'destroy'])->name('destroy');
        });

        // Photo Sessions — DISABLED (2026-07): schools issue ID cards before the
        // first class, so the mobile "photograph during session" flow is not
        // useful in practice. Commented out (not deleted) so it can be restored
        // wholesale if the workflow is ever needed again. See also:
        // routes/api.php (photo-sessions), SchoolLayout.tsx (nav), and the
        // mobile Photos tab in main.dart.
        // Route::prefix('photo-sessions')->name('school.photo-sessions.')->group(function () {
        //     Route::get('/',                               [SchoolPhotoSessionController::class, 'index'])->name('index');
        //     Route::get('/create',                         [SchoolPhotoSessionController::class, 'create'])->name('create');
        //     Route::post('/',                              [SchoolPhotoSessionController::class, 'store'])->name('store');
        //     Route::get('/{photoSession}',                 [SchoolPhotoSessionController::class, 'show'])->name('show');
        //     // PRD §08: photo upload limited to 30 requests/minute.
        //     Route::post('/{photoSession}/upload-photo',   [SchoolPhotoSessionController::class, 'uploadPhoto'])->name('upload-photo')->middleware('throttle:photo-upload');
        //     Route::post('/{photoSession}/skip',           [SchoolPhotoSessionController::class, 'skipStudent'])->name('skip');
        //     Route::post('/{photoSession}/upload-zip',     [SchoolPhotoSessionController::class, 'uploadZip'])->name('upload-zip')->middleware('throttle:photo-upload');
        //     Route::post('/{photoSession}/complete',       [SchoolPhotoSessionController::class, 'complete'])->name('complete');
        //     Route::post('/{photoSession}/cancel',         [SchoolPhotoSessionController::class, 'cancel'])->name('cancel');
        // });

        // Card Orders (school side: select students → confirmation code → track)
        Route::get('/card-orders/{cardOrder}/id-cards', [SchoolIdCardController::class, 'batch'])
            ->name('school.card-orders.id-cards');
        Route::prefix('card-orders')->name('school.card-orders.')->group(function () {
            Route::get('/',                   [SchoolCardOrderController::class, 'index'])->name('index');
            Route::get('/create',             [SchoolCardOrderController::class, 'create'])->name('create');
            // PRD §08: card order creation limited to 5 requests/hour.
            Route::post('/',                  [SchoolCardOrderController::class, 'store'])->name('store')->middleware('throttle:card-order');
            Route::get('/{cardOrder}',        [SchoolCardOrderController::class, 'show'])->name('show');
            Route::post('/{cardOrder}/cancel', [SchoolCardOrderController::class, 'cancel'])->name('cancel');
        });
    });

// Ring 3 — school admins + support staff (registry pages; writes are
// additionally requireAdmin()-guarded inside the controllers).
Route::middleware(['auth', 'role:school_admin,staff', 'school.scope'])->prefix('school')->group(function () {
        // ── Student lifecycle ────────────────────────────────────
        // Registered before the students resource so that a literal segment
        // ("lifecycle", "documents") is never mistaken for a student id.
        // Writes are additionally requireAdmin()-guarded inside the controller.
        Route::prefix('students/{student}/lifecycle')->name('school.students.lifecycle.')
            ->group(function () {
                Route::post('/enroll',       [SchoolStudentLifecycleController::class, 'enroll'])->name('enroll');
                Route::post('/promote',      [SchoolStudentLifecycleController::class, 'promote'])->name('promote');
                Route::post('/repeat',       [SchoolStudentLifecycleController::class, 'repeat'])->name('repeat');
                Route::post('/change-class', [SchoolStudentLifecycleController::class, 'changeClass'])->name('change-class');
                Route::post('/transfer',     [SchoolStudentLifecycleController::class, 'transfer'])->name('transfer');
                Route::post('/withdraw',     [SchoolStudentLifecycleController::class, 'withdraw'])->name('withdraw');
                Route::post('/graduate',     [SchoolStudentLifecycleController::class, 'graduate'])->name('graduate');
                Route::post('/reinstate',    [SchoolStudentLifecycleController::class, 'reinstate'])->name('reinstate');
            });

        Route::patch('students/{student}/profile', [SchoolStudentLifecycleController::class, 'updateProfile'])
            ->name('school.students.profile.update');

        Route::prefix('students/{student}/documents')->name('school.students.documents.')
            ->group(function () {
                Route::post('/',            [SchoolStudentLifecycleController::class, 'storeDocument'])->name('store');
                Route::get('/{document}',   [SchoolStudentLifecycleController::class, 'showDocument'])->name('show');
                Route::get('/{document}/download', [SchoolStudentLifecycleController::class, 'downloadDocument'])->name('download');
                Route::delete('/{document}', [SchoolStudentLifecycleController::class, 'destroyDocument'])->name('destroy');
                Route::post('/{document}/verify', [SchoolStudentLifecycleController::class, 'verifyDocument'])->name('verify');
            });

        // Admissions pipeline — enquiry through to enrolment.
        Route::prefix('admissions')->name('school.admissions.')->group(function () {
            Route::get('/',              [SchoolAdmissionController::class, 'index'])->name('index');
            Route::get('/create',        [SchoolAdmissionController::class, 'create'])->name('create');
            Route::post('/',             [SchoolAdmissionController::class, 'store'])->name('store');
            Route::get('/{application}', [SchoolAdmissionController::class, 'show'])->name('show');
            Route::patch('/{application}', [SchoolAdmissionController::class, 'update'])->name('update');
            Route::delete('/{application}', [SchoolAdmissionController::class, 'destroy'])->name('destroy');
            Route::post('/{application}/status',  [SchoolAdmissionController::class, 'changeStatus'])->name('status');
            Route::post('/{application}/convert', [SchoolAdmissionController::class, 'convert'])->name('convert');
        });

        // End-of-year bulk promotion for a whole cohort.
        Route::prefix('promotions')->name('school.promotions.')->group(function () {
            Route::get('/',  [SchoolBulkPromotionController::class, 'index'])->name('index');
            Route::post('/', [SchoolBulkPromotionController::class, 'store'])->name('store');
        });

        // Programmes of study (the layer above classes).
        Route::prefix('programmes')->name('school.programmes.')->group(function () {
            Route::get('/',              [SchoolProgrammeController::class, 'index'])->name('index');
            Route::post('/',             [SchoolProgrammeController::class, 'store'])->name('store');
            Route::patch('/{programme}', [SchoolProgrammeController::class, 'update'])->name('update');
            Route::delete('/{programme}', [SchoolProgrammeController::class, 'destroy'])->name('destroy');
        });

        Route::resource('/students', SchoolStudentController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
            ->names('school.students');
        Route::resource('/classes', SchoolClassController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->names('school.classes');
    });
