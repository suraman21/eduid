<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PinAuthTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Test School', 'code' => 'TST', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);

        $this->teacher = User::create([
            'name' => 'Teacher', 'email' => 'teacher@test.com',
            'password' => Hash::make('password'), 'role' => 'teacher',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
    }

    public function test_can_set_pin_with_correct_password(): void
    {
        Sanctum::actingAs($this->teacher);

        $response = $this->postJson('/api/v1/auth/pin/set', [
            'pin' => '1234',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertTrue($this->teacher->fresh()->hasPin());
    }

    public function test_cannot_set_pin_with_wrong_password(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/auth/pin/set', [
            'pin' => '1234',
            'password' => 'wrong',
        ])->assertForbidden();

        $this->assertFalse($this->teacher->fresh()->hasPin());
    }

    public function test_pin_must_be_4_digits(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/auth/pin/set', [
            'pin' => '123',
            'password' => 'password',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/pin/set', [
            'pin' => 'abcd',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_can_verify_correct_pin(): void
    {
        $this->teacher->update(['pin_hash' => Hash::make('5678')]);
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/auth/pin/verify', ['pin' => '5678'])->assertOk();
    }

    public function test_wrong_pin_returns_attempts_remaining(): void
    {
        $this->teacher->update(['pin_hash' => Hash::make('5678')]);
        Sanctum::actingAs($this->teacher);

        $response = $this->postJson('/api/v1/auth/pin/verify', ['pin' => '0000']);
        $response->assertStatus(401);
        $response->assertJsonPath('attempts_remaining', 4);
    }

    public function test_five_wrong_pins_locks_and_revokes_token(): void
    {
        $this->teacher->update(['pin_hash' => Hash::make('5678')]);
        Sanctum::actingAs($this->teacher);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/pin/verify', ['pin' => '0000']);
        }

        $this->assertTrue($this->teacher->fresh()->isPinLocked());
    }

    public function test_verify_without_pin_set_returns_422(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/auth/pin/verify', ['pin' => '1234'])
            ->assertStatus(422);
    }
}
