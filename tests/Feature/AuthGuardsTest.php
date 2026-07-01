<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Dual-guard auth: the school portal (web guard) and super-admin portal
 * (admin guard) must coexist in ONE browser session. The old single-guard
 * setup made the second login evict the first — causing surprise 403s and
 * stale-CSRF 419s in the other tab.
 */
class AuthGuardsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $schoolAdmin;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $this->schoolAdmin = User::create([
            'name' => 'Admin', 'email' => 'admin@school.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@arkeon.com', 'password' => Hash::make('password'),
            'role' => 'super_admin', 'school_id' => null, 'is_active' => true,
        ]);
    }

    private function loginSchool(): void
    {
        $this->post('/login', [
            'school_code' => 'ALPHA', 'email' => 'admin@school.com', 'password' => 'password',
        ]);
    }

    private function loginAdmin(): void
    {
        $this->post('/admin/login', [
            'email' => 'super@arkeon.com', 'password' => 'password',
        ]);
    }

    public function test_school_and_admin_logins_coexist_in_one_session(): void
    {
        $this->loginSchool();
        $this->loginAdmin();

        // BOTH portals now work — neither login evicted the other.
        $this->get('/school/dashboard')->assertOk();
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_admin_login_does_not_evict_school_session(): void
    {
        $this->loginSchool();
        $this->get('/school/dashboard')->assertOk();

        $this->loginAdmin();

        // The regression: this used to 403/redirect because the admin login
        // replaced the school user on the shared web guard.
        $this->get('/school/dashboard')->assertOk();
    }

    public function test_admin_logout_keeps_school_session_alive(): void
    {
        $this->loginSchool();
        $this->loginAdmin();

        $this->post('/admin/logout')->assertRedirect('/admin/login');

        $this->get('/school/dashboard')->assertOk();          // school survives
        // Admin access is gone (redirect to login, or 403 when the web-guard
        // school user is picked up by auth:admin,web — never 200).
        $this->assertNotSame(200, $this->get('/admin/dashboard')->status());
    }

    public function test_school_logout_keeps_admin_session_alive(): void
    {
        $this->loginSchool();
        $this->loginAdmin();

        $this->post('/logout')->assertRedirect('/');

        $this->get('/admin/dashboard')->assertOk();           // admin survives
    }

    public function test_school_user_cannot_reach_admin_routes(): void
    {
        $this->loginSchool();

        $this->get('/admin/dashboard')->assertStatus(403);
    }

    public function test_admin_guard_alone_grants_admin_access(): void
    {
        $this->loginAdmin();

        $this->get('/admin/dashboard')->assertOk();
        // But not the school portal — no web-guard login exists (redirect to
        // login, or 403 via the lingering in-test default guard — never 200).
        $this->assertNotSame(200, $this->get('/school/dashboard')->status());
    }

    public function test_expired_csrf_on_logout_redirects_to_login_not_419(): void
    {
        // CSRF middleware is skipped in the test runner, so exercise the
        // exception renderable directly: a TokenMismatch on the logout routes
        // must produce a login redirect, never the bare 419 page.
        $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        $schoolLogout = \Illuminate\Http\Request::create('/logout', 'POST');
        $response = $handler->render($schoolLogout, new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));

        $adminLogout = \Illuminate\Http\Request::create('/admin/logout', 'POST');
        $response = $handler->render($adminLogout, new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/admin/login', $response->headers->get('Location'));
    }

    public function test_custom_404_page_carries_support_code(): void
    {
        $response = $this->get('/definitely-not-a-real-page');

        $response->assertNotFound();
        $response->assertSee('EDU-404');
    }
}
