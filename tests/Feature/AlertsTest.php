<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\AlertService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Telegram absence alerts: outbox enqueue on attendance save (linked
 * guardians of absent students only, idempotent), flush via the Bot API,
 * webhook guardian-linking with HMAC tokens, and the 'alerts' module gate.
 */
class AlertsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private SchoolClass $class;
    private Student $absentee;
    private Student $present;
    private Guardian $linked;
    private Guardian $unlinked;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token'      => 'test-token',
            'services.telegram.bot_username'   => 'eduid_test_bot',
            'services.telegram.webhook_secret' => 'hook-secret',
        ]);

        // Premium tier — includes 'alerts' per the seeded matrix.
        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 1000, 'max_teachers' => 100,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->class = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 2', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
        $this->absentee = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-001',
            'first_name' => 'Sara', 'last_name' => 'Kebede', 'gender' => 'female',
            'status' => 'active', 'enrollment_date' => today(), 'class_id' => $this->class->id,
        ]);
        $this->present = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-002',
            'first_name' => 'Abel', 'last_name' => 'Tesfaye', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(), 'class_id' => $this->class->id,
        ]);

        $this->linked = Guardian::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'full_name' => 'Kebede Alemu',
            'relationship' => 'father', 'telegram_chat_id' => '111222333',
        ]);
        $this->linked->students()->attach($this->absentee->id, ['is_primary' => true]);

        $this->unlinked = Guardian::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'full_name' => 'No Telegram',
            'relationship' => 'mother',
        ]);
        $this->unlinked->students()->attach($this->absentee->id, ['is_primary' => false]);
        // Present student's guardian must never be alerted.
        $this->linked->students()->attach($this->present->id, ['is_primary' => false]);
    }

    private function saveAttendance(): void
    {
        $this->actingAs($this->admin)->post('/school/attendance', [
            'class_id'     => $this->class->id,
            'session_date' => today()->toDateString(),
            'session_type' => 'morning',
            'records'      => [
                ['student_id' => $this->absentee->id, 'status' => 'absent'],
                ['student_id' => $this->present->id, 'status' => 'present'],
            ],
        ])->assertRedirect();
    }

    public function test_absence_enqueues_and_sends_alert_to_linked_guardians_only(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->saveAttendance();

        // One outbox row: absent student × LINKED guardian. The unlinked
        // guardian and the present student's guardian get nothing.
        $this->assertDatabaseCount('alert_outbox', 1);
        $this->assertDatabaseHas('alert_outbox', [
            'guardian_id' => $this->linked->id,
            'student_id'  => $this->absentee->id,
            'status'      => 'sent',
        ]);

        Http::assertSent(fn ($request) =>
            str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === '111222333'
            && str_contains($request['text'], 'Sara Kebede')
            && str_contains($request['text'], 'ABSENT'));
    }

    public function test_resaving_attendance_never_respams(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->saveAttendance();
        $this->saveAttendance(); // same slot re-saved (edit flow)

        $this->assertDatabaseCount('alert_outbox', 1);
        Http::assertSentCount(1);
    }

    public function test_failed_sends_stay_pending_for_the_cron_retry(): void
    {
        // First call fails (Telegram down), later calls succeed — fakes
        // stack first-match-wins, so one stateful stub handles both phases.
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            return $calls === 1
                ? Http::response(['ok' => false, 'description' => 'chat not found'], 400)
                : Http::response(['ok' => true]);
        });

        $this->saveAttendance();

        $this->assertDatabaseHas('alert_outbox', [
            'guardian_id' => $this->linked->id, 'status' => 'pending', 'attempts' => 1,
        ]);

        // Cron retry succeeds later.
        $this->artisan('alerts:send')->assertSuccessful();

        $this->assertDatabaseHas('alert_outbox', [
            'guardian_id' => $this->linked->id, 'status' => 'sent',
        ]);
    }

    public function test_no_alerts_when_module_not_in_package(): void
    {
        Http::fake();
        $this->school->update(['plan_tier' => 'starter']);
        $this->school->flushFeatureCache();

        $this->saveAttendance();

        $this->assertDatabaseCount('alert_outbox', 0);
        Http::assertNothingSent();
    }

    // ── Webhook linking ──────────────────────────────────────────

    public function test_webhook_links_guardian_via_signed_token(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $token = app(TelegramService::class)->linkToken($this->unlinked);

        $this->postJson('/api/v1/telegram/webhook', [
            'message' => ['chat' => ['id' => 999888], 'text' => "/start {$token}"],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'hook-secret'])->assertOk();

        $this->assertSame('999888', $this->unlinked->fresh()->telegram_chat_id);
    }

    public function test_webhook_rejects_forged_tokens_and_wrong_secret(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        // Forged signature — no link.
        $this->postJson('/api/v1/telegram/webhook', [
            'message' => ['chat' => ['id' => 777], 'text' => '/start g' . $this->unlinked->id . '.deadbeefdeadbeef'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'hook-secret'])->assertOk();
        $this->assertNull($this->unlinked->fresh()->telegram_chat_id);

        // Wrong webhook secret — ignored entirely, no outbound call.
        Http::fake();
        $token = app(TelegramService::class)->linkToken($this->unlinked);
        $this->postJson('/api/v1/telegram/webhook', [
            'message' => ['chat' => ['id' => 777], 'text' => "/start {$token}"],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])->assertOk();
        $this->assertNull($this->unlinked->fresh()->telegram_chat_id);
        Http::assertNothingSent();
    }

    public function test_deep_link_is_null_without_bot_config(): void
    {
        config(['services.telegram.bot_token' => null, 'services.telegram.bot_username' => null]);

        $this->assertNull(app(TelegramService::class)->deepLink($this->linked));

        // And flushing without a bot is a graceful no-op.
        [$sent, $failed] = app(AlertService::class)->flush();
        $this->assertSame([0, 0], [$sent, $failed]);
    }
}
