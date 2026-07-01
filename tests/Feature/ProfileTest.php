<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
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
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@school.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@arkeon.com', 'password' => Hash::make('password'),
            'role' => 'super_admin', 'school_id' => null, 'is_active' => true,
        ]);
    }

    public function test_school_user_can_view_profile(): void
    {
        $this->actingAs($this->admin)->get('/school/profile')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('School/Profile', false)
                ->where('profile.email', 'admin@school.com')
                ->where('profile.school.code', 'ALPHA'));
    }

    public function test_super_admin_can_view_profile_on_admin_portal(): void
    {
        $this->actingAs($this->superAdmin, 'admin')->get('/admin/profile')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/Profile', false)
                ->where('profile.role', 'super_admin'));
    }

    public function test_name_and_phone_update_without_password(): void
    {
        $this->actingAs($this->admin)->patch('/school/profile', [
            'name' => 'New Name', 'email' => 'admin@school.com', 'phone' => '+251911000000',
        ])->assertRedirect();

        $this->admin->refresh();
        $this->assertSame('New Name', $this->admin->name);
        $this->assertSame('+251911000000', $this->admin->phone);
    }

    public function test_email_change_requires_current_password(): void
    {
        // Without the password → rejected, email unchanged.
        $this->actingAs($this->admin)->patch('/school/profile', [
            'name' => 'Admin', 'email' => 'new@school.com',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame('admin@school.com', $this->admin->fresh()->email);

        // Wrong password → rejected.
        $this->actingAs($this->admin)->patch('/school/profile', [
            'name' => 'Admin', 'email' => 'new@school.com', 'current_password' => 'wrong',
        ])->assertSessionHasErrors('current_password');

        // Correct password → changed + audit-logged.
        $this->actingAs($this->admin)->patch('/school/profile', [
            'name' => 'Admin', 'email' => 'new@school.com', 'current_password' => 'password',
        ])->assertRedirect();
        $this->assertSame('new@school.com', $this->admin->fresh()->email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.profile_updated', 'user_id' => $this->admin->id]);
    }

    public function test_email_uniqueness_is_scoped_to_the_school(): void
    {
        $other = School::create([
            'name' => 'Beta', 'code' => 'BETA', 'is_active' => true, 'plan_tier' => 'premium',
            'max_students' => 100, 'max_teachers' => 10, 'hmac_secret' => 'x',
            'academic_year' => '2018 E.C.', 'subscription_status' => 'active',
        ]);
        User::create([
            'name' => 'Other', 'email' => 'shared@mail.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $other->id, 'is_active' => true,
        ]);

        // Same email exists at ANOTHER school — allowed (per-school uniqueness).
        $this->actingAs($this->admin)->patch('/school/profile', [
            'name' => 'Admin', 'email' => 'shared@mail.com', 'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $this->actingAs($this->admin)->put('/school/profile/password', [
            'current_password' => 'wrong', 'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $this->admin->fresh()->password));
    }

    public function test_password_change_works_and_revokes_mobile_tokens(): void
    {
        $this->admin->createToken('phone');
        $this->assertSame(1, $this->admin->tokens()->count());

        $this->actingAs($this->admin)->put('/school/profile/password', [
            'current_password' => 'password', 'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9',
        ])->assertRedirect();

        $this->admin->refresh();
        $this->assertTrue(Hash::check('new-secret-9', $this->admin->password));
        $this->assertSame(0, $this->admin->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_changed', 'user_id' => $this->admin->id]);
        // Still signed in (session rotated, not destroyed).
        $this->get('/school/profile')->assertOk();
    }

    public function test_guest_cannot_reach_profile(): void
    {
        $this->get('/school/profile')->assertRedirect();
        $this->get('/admin/profile')->assertRedirect();
    }
}
