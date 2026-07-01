<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PRD §15: super-admin logins require an emailed OTP on every sign-in.
 * The password step alone must never authenticate the admin guard.
 */
class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('auth.admin_2fa', true);

        $this->superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@arkeon.com', 'password' => Hash::make('password'),
            'role' => 'super_admin', 'school_id' => null, 'is_active' => true,
        ]);
    }

    /** Plant a known pending-2FA state so the code is testable. */
    private function plantOtp(string $otp, array $overrides = []): void
    {
        session()->put('admin_2fa', array_merge([
            'user_id'    => $this->superAdmin->id,
            'otp_hash'   => Hash::make($otp),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'attempts'   => 0,
            'remember'   => false,
        ], $overrides));
    }

    public function test_password_alone_does_not_authenticate_and_redirects_to_verify(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'super@arkeon.com', 'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.otp'));
        $this->assertGuest('admin');
        $this->assertTrue(session()->has('admin_2fa'));
    }

    public function test_correct_otp_completes_the_login(): void
    {
        $this->plantOtp('123456');

        $this->post('/admin/login/verify', ['otp' => '123456'])
            ->assertRedirect('/admin/dashboard');

        $this->assertAuthenticated('admin');
        $this->assertFalse(session()->has('admin_2fa'));
    }

    public function test_wrong_otp_is_rejected_and_attempts_increment(): void
    {
        $this->plantOtp('123456');

        $this->post('/admin/login/verify', ['otp' => '000000'])->assertSessionHasErrors('otp');

        $this->assertGuest('admin');
        $this->assertSame(1, session('admin_2fa.attempts'));
    }

    public function test_attempt_cap_clears_state_even_with_the_right_code(): void
    {
        // 5 failures already recorded (the route throttle also limits to
        // 5/15min as defense-in-depth) — even the CORRECT code is now refused.
        $this->plantOtp('123456', ['attempts' => 5]);

        $this->post('/admin/login/verify', ['otp' => '123456'])
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
        $this->assertFalse(session()->has('admin_2fa'));
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->plantOtp('123456', ['expires_at' => now()->subMinute()->timestamp]);

        $this->post('/admin/login/verify', ['otp' => '123456'])
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
        $this->assertFalse(session()->has('admin_2fa'));
    }

    public function test_verify_page_requires_pending_state(): void
    {
        $this->get('/admin/login/verify')->assertRedirect(route('admin.login'));
    }

    public function test_kill_switch_allows_direct_login(): void
    {
        config()->set('auth.admin_2fa', false);

        $this->post('/admin/login', [
            'email' => 'super@arkeon.com', 'password' => 'password',
        ])->assertRedirect('/admin/dashboard');

        $this->assertAuthenticated('admin');
    }
}
