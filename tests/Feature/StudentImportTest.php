<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 1000, 'max_teachers' => 100,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);

        // Seed the feature registry + plan features; premium includes csv_import.
        $this->seed(\Database\Seeders\FeatureSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->class = SchoolClass::create([
            'school_id' => $this->school->id, 'grade' => 'Grade 5', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'level_order' => 6, 'is_active' => true,
        ]);
    }

    private function upload(string $csv): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv') . '.csv';
        file_put_contents($path, $csv);

        return new UploadedFile($path, 'students.csv', 'text/csv', null, true);
    }

    public function test_valid_csv_imports_students_with_class_and_guardian(): void
    {
        $csv = "first_name,last_name,gender,grade,section,date_of_birth,guardian_name,guardian_phone,guardian_relationship\n"
             . "Abebe,Kebede,male,Grade 5,A,15/03/2010,Kedir Kebede,0911000001,father\n";

        $preview = $this->actingAs($this->admin)
            ->post('/school/students/import', ['csv_file' => $this->upload($csv)]);
        $preview->assertOk();

        $tmpKey = $preview->viewData('tmpKey');
        $this->assertNotEmpty($tmpKey);

        $this->actingAs($this->admin)
            ->post('/school/students/import/confirm', ['tmp_key' => $tmpKey])
            ->assertRedirect(route('school.students.index'));

        $student = Student::withoutGlobalScope('school')->first();
        $this->assertNotNull($student);
        $this->assertSame('Abebe', $student->first_name);
        $this->assertSame($this->class->id, $student->class_id);
        $this->assertSame('2010-03-15', $student->date_of_birth->toDateString());
        $this->assertStringStartsWith('ALPHA-', $student->uid);
        $this->assertSame(1, $student->guardians()->count());
    }

    public function test_rows_with_errors_are_flagged_and_skipped(): void
    {
        // Row 2 is valid; row 3 has a bad gender and an unknown class.
        $csv = "first_name,last_name,gender,grade,section\n"
             . "Abebe,Kebede,male,Grade 5,A\n"
             . "Sara,Tesfaye,X,Grade 9,Z\n";

        $preview = $this->actingAs($this->admin)
            ->post('/school/students/import', ['csv_file' => $this->upload($csv)]);
        $preview->assertOk();

        $results = collect($preview->viewData('preview'));
        $this->assertTrue($results[0]['valid']);
        $this->assertFalse($results[1]['valid']);

        // Confirm imports only the valid row.
        $this->actingAs($this->admin)
            ->post('/school/students/import/confirm', ['tmp_key' => $preview->viewData('tmpKey')]);

        $this->assertSame(1, Student::withoutGlobalScope('school')->count());
    }

    public function test_invalid_ethiopian_phone_is_rejected(): void
    {
        // First row valid so the controller returns the preview view (validCount > 0);
        // the second row has an invalid phone and must be flagged.
        $csv = "first_name,last_name,gender,guardian_name,guardian_phone\n"
             . "Abebe,Kebede,male,Kedir,0911000001\n"
             . "Sara,Tesfaye,female,Almaz,12345\n";

        $preview = $this->actingAs($this->admin)
            ->post('/school/students/import', ['csv_file' => $this->upload($csv)]);
        $preview->assertOk();

        $results = collect($preview->viewData('preview'));
        $this->assertTrue($results[0]['valid']);
        $this->assertFalse($results[1]['valid']);
    }

    public function test_import_blocked_when_feature_disabled(): void
    {
        DB::table('school_feature_overrides')->insert([
            'school_id'   => $this->school->id,
            'feature_key' => 'csv_import',
            'is_enabled'  => false,
        ]);
        \Illuminate\Support\Facades\Cache::forget("school.{$this->school->id}.feature.csv_import");

        $this->actingAs($this->admin)
            ->get('/school/students/import')
            ->assertForbidden();
    }
}
