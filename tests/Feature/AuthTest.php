<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the default Super Admin user
        User::create([
            'name' => 'Super Admin',
            'email' => 'suraemu21@gmail.com',
            'password' => Hash::make('EduID@2026!'),
            'role' => User::ROLE_SUPER_ADMIN,
            'school_id' => null,
            'is_active' => true,
        ]);

        // Create a test school and a school admin
        $this->school = School::create([
            'name' => 'Abyssinia Academy',
            'code' => 'AIA',
            'is_active' => true,
            'plan_tier' => 'premium',
            'hmac_secret' => 'test-hmac-secret',
            'academic_year' => '2018 E.C.',
        ]);

        $this->schoolAdmin = User::create([
            'name' => 'School Admin',
            'email' => 'admin@abyssinia.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test home page shows marketing page for guests.
     */
    public function test_home_page_shows_marketing_page(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('EduID');
    }

    /**
     * Test health check endpoint.
     */
    public function test_api_health_check(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'db', 'storage', 'version', 'time']);
    }

    /**
     * Test web login pages load.
     */
    public function test_login_pages_load(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }

    /**
     * Test Super Admin login success.
     */
    public function test_super_admin_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'suraemu21@gmail.com',
            'password' => 'EduID@2026!',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticated();
    }

    /**
     * Test Super Admin login failure.
     */
    public function test_super_admin_cannot_login_with_invalid_password(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'suraemu21@gmail.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Test School Admin login success.
     */
    public function test_school_admin_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/login', [
            'school_code' => 'AIA',
            'email' => 'admin@abyssinia.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/school/dashboard');
        $this->assertAuthenticated();
        
        // Check session school_id
        $this->assertEquals($this->school->id, session('school_id'));
    }

    /**
     * Test School Admin login fails for inactive school.
     */
    public function test_school_admin_cannot_login_if_school_deactivated(): void
    {
        $this->school->update(['is_active' => false]);

        $response = $this->post('/login', [
            'school_code' => 'AIA',
            'email' => 'admin@abyssinia.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('school_code');
        $this->assertGuest();
    }

    /**
     * Test API login success and token generation.
     */
    public function test_api_login_returns_token_and_validates(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'school_code' => 'AIA',
            'email' => 'admin@abyssinia.com',
            'password' => 'password123',
            'device_name' => 'iPhone',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user', 'school']);
        
        $token = $response->json('token');

        // Test authenticated endpoint /api/v1/auth/me
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200);
        $meResponse->assertJsonFragment(['email' => 'admin@abyssinia.com']);
    }

    /**
     * Test account lockout after 5 attempts.
     */
    public function test_account_lockout_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'school_code' => 'AIA',
                'email' => 'admin@abyssinia.com',
                'password' => 'wrongpassword',
            ]);
        }

        // 6th attempt should return a lockout message
        $response = $this->post('/login', [
            'school_code' => 'AIA',
            'email' => 'admin@abyssinia.com',
            'password' => 'password123', // correct password, but locked
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        
        // Assert the user locked_until is set in database
        $this->schoolAdmin->refresh();
        $this->assertNotNull($this->schoolAdmin->locked_until);
        $this->assertTrue(now()->lessThan($this->schoolAdmin->locked_until));
    }

    public function test_user_with_can_login_false_is_rejected(): void
    {
        $this->schoolAdmin->update(['can_login' => false]);

        $response = $this->post('/login', [
            'school_code' => 'AIA',
            'email'       => 'admin@abyssinia.com',
            'password'    => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
