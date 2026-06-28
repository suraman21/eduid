<?php

namespace Tests\Feature;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchool(): School
    {
        return School::create([
            'name' => 'Sec School', 'code' => 'SEC', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'super-secret-key', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
    }

    public function test_school_hmac_secret_is_never_serialized(): void
    {
        $school = $this->makeSchool();

        // The QR-signing secret must never reach the client via any serialization
        // path — array, JSON, or Inertia props (all funnel through toArray()).
        $this->assertArrayNotHasKey('hmac_secret', $school->toArray());
        $this->assertStringNotContainsString('super-secret-key', $school->toJson());
        $this->assertStringNotContainsString('hmac_secret', $school->toJson());

        // But it is still readable server-side for token generation.
        $this->assertSame('super-secret-key', $school->hmac_secret);
    }
}
