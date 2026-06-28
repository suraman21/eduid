<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name'          => 'Abyssinia Academy',
            'code'          => 'AIA',
            'is_active'     => true,
            'plan_tier'     => 'premium',
            'hmac_secret'   => 'test-hmac-secret',
            'academic_year' => '2018 E.C.',
        ]);

        $this->user = User::create([
            'name'      => 'School Admin',
            'email'     => 'admin@abyssinia.com',
            'password'  => Hash::make('original-password'),
            'role'      => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $this->school->id,
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    public function test_forgot_password_form_is_reachable(): void
    {
        // Inertia/React page — assert the resolved component, not server HTML text.
        $this->get('/forgot-password')->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword', false));
    }

    public function test_requesting_a_code_stores_a_hashed_otp_and_sends_mail(): void
    {
        Mail::fake();

        $response = $this->post('/forgot-password', [
            'school_code' => 'AIA',
            'email'       => 'admin@abyssinia.com',
        ]);

        $response->assertRedirect();

        $record = DB::table('password_reset_otps')
            ->where('school_id', $this->school->id)
            ->where('email', 'admin@abyssinia.com')
            ->first();

        $this->assertNotNull($record);
        // Stored hashed, never in plaintext.
        $this->assertNotEquals('', $record->otp_hash);
        $this->assertEquals(60, strlen($record->otp_hash));
    }

    public function test_unknown_account_does_not_leak_existence(): void
    {
        Mail::fake();

        $response = $this->post('/forgot-password', [
            'school_code' => 'AIA',
            'email'       => 'nobody@nowhere.com',
        ]);

        // Neutral redirect with a generic status — no error, no mail.
        $response->assertRedirect();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_valid_otp_resets_the_password(): void
    {
        $otp = '123456';
        DB::table('password_reset_otps')->insert([
            'school_id'  => $this->school->id,
            'email'      => 'admin@abyssinia.com',
            'otp_hash'   => Hash::make($otp),
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        $response = $this->post('/reset-password', [
            'school_code'           => 'AIA',
            'email'                 => 'admin@abyssinia.com',
            'otp'                   => $otp,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertRedirect(route('login'));

        $this->user->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $this->user->password));
        // Code consumed.
        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_wrong_otp_is_rejected_and_increments_attempts(): void
    {
        DB::table('password_reset_otps')->insert([
            'school_id'  => $this->school->id,
            'email'      => 'admin@abyssinia.com',
            'otp_hash'   => Hash::make('123456'),
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        $response = $this->from('/reset-password')->post('/reset-password', [
            'school_code'           => 'AIA',
            'email'                 => 'admin@abyssinia.com',
            'otp'                   => '999999',
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertSessionHasErrors('otp');

        $this->user->refresh();
        $this->assertTrue(Hash::check('original-password', $this->user->password));

        $record = DB::table('password_reset_otps')->first();
        $this->assertEquals(1, $record->attempts);
    }

    public function test_expired_otp_is_rejected(): void
    {
        DB::table('password_reset_otps')->insert([
            'school_id'  => $this->school->id,
            'email'      => 'admin@abyssinia.com',
            'otp_hash'   => Hash::make('123456'),
            'attempts'   => 0,
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(11),
        ]);

        $response = $this->from('/reset-password')->post('/reset-password', [
            'school_code'           => 'AIA',
            'email'                 => 'admin@abyssinia.com',
            'otp'                   => '123456',
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertSessionHasErrors('otp');

        $this->user->refresh();
        $this->assertTrue(Hash::check('original-password', $this->user->password));
    }
}
