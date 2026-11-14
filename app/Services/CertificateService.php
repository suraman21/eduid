<?php

namespace App\Services;

use App\Models\School;
use App\Models\StudentGrade;
use App\Models\Student;
use App\Models\StudentEnrollment;

/**
 * Turns a student's own records into the wording of a certificate.
 *
 * Nothing here is invented: dates, classes and results come from enrolments
 * and published marks. The one exception is a character reference, where the
 * sentence about conduct is the registrar's to write — the service only offers
 * a neutral draft that must be confirmed on the form.
 */
class CertificateService
{
    public function __construct(private TranscriptService $transcripts)
    {
    }

    /**
     * Build the letter for a student.
     *
     * @param  array{addressed_to?: ?string, body_note?: ?string, issued_on?: ?string}  $options
     */
    public function build(Student $student, string $type, array $options = []): array
    {
        $school = $student->school;

        $enrollments = StudentEnrollment::withoutGlobalScopes()
            ->with(['cohort:id,grade,section', 'programme:id,name'])
            ->where('student_id', $student->id)
            ->orderBy('enrolled_on')
            ->get();

        $current = $enrollments->firstWhere('is_current', true) ?? $enrollments->last();
        $first   = $enrollments->first();

        $pronoun = $this->pronouns($student->gender);
        $name    = $student->full_name;
        $level   = $this->levelLabel($current);

        $facts = $this->facts($student, $enrollments, $current, $first);

        [$title, $paragraphs, $closing] = match ($type) {
            'transfer'   => $this->transfer($student, $school, $name, $level, $enrollments, $first, $current, $pronoun),
            'completion' => $this->completion($student, $school, $name, $level, $current, $pronoun),
            'character'  => $this->character($student, $school, $name, $first, $current, $pronoun, $options),
            'bonafide'   => $this->bonafide($student, $school, $name, $level, $current, $pronoun),
            default      => $this->custom($options),
        };

        return [
            'type'          => $type,
            'title'         => $title,
            'addressed_to'  => $options['addressed_to'] ?? null,
            'body_note'     => $options['body_note'] ?? null,
            'paragraphs'    => $paragraphs,
            'closing'       => $closing,
            'facts'         => $facts,
            'student'       => [
                'id'            => $student->id,
                'uid'           => $student->uid,
                'full_name'     => $student->full_name,
                'full_name_am'  => $student->full_name_am,
                'gender'        => $student->gender,
                'date_of_birth' => $student->date_of_birth?->format('j F Y'),
            ],
            'school'        => [
                'name'    => $school?->name,
                'address' => $school?->address,
                'phone'   => $school?->phone ?? null,
                'email'   => $school?->email ?? null,
            ],
            'generated_on'  => now()->format('j F Y'),
        ];
    }

    /** A neutral conduct sentence offered to the registrar as a starting point. */
    public function suggestedConductNote(Student $student): string
    {
        $p = $this->pronouns($student->gender);

        return "During {$p['possessive']} time with us {$student->full_name} was regular in attendance and conducted {$p['reflexive']} in a manner consistent with the school's expectations.";
    }

    // ── Letter bodies ───────────────────────────────────────────

    private function transfer(
        Student $student,
        ?School $school,
        string $name,
        ?string $level,
        $enrollments,
        ?StudentEnrollment $first,
        ?StudentEnrollment $current,
        array $p
    ): array {
        $from   = $first?->enrolled_on?->format('j F Y');
        $ended  = $current?->ended_on ?? $student->end_date;
        $active = $current?->is_current === true && $ended === null;

        $paras = [];

        if ($active) {
            // Still on roll: the letter must not claim a period that has not
            // ended, and must never print "from X to X" for a same-day record.
            $paras[] = $from
                ? "This is to certify that {$name} (student ID {$student->uid}) is currently a student of {$school?->name}, enrolled since {$from}."
                : "This is to certify that {$name} (student ID {$student->uid}) is currently a student of {$school?->name}.";
        } else {
            $to = $ended?->format('j F Y');

            $paras[] = match (true) {
                $from !== null && $to !== null && $from !== $to
                    => "This is to certify that {$name} (student ID {$student->uid}) was a bona fide student of {$school?->name} from {$from} to {$to}.",
                default => "This is to certify that {$name} (student ID {$student->uid}) was a bona fide student of {$school?->name}.",
            };
        }

        if ($level) {
            // Capitalise when the pronoun opens the sentence.
            $Subject = ucfirst($p['subject']);

            $paras[] = $active
                ? "{$Subject} is presently enrolled in {$level}."
                : "During {$p['possessive']} final period of study the student was enrolled in {$level}.";
        }

        $destination = $current?->destination_school;
        $reason      = $current?->exit_reason;

        $leaving = "This certificate is issued at the request of {$p['possessive']} family in support of a transfer";
        $leaving .= $destination ? " to {$destination}." : " to another institution.";
        $paras[] = $leaving;

        if ($reason && ! in_array($reason, ['transfer'], true)) {
            $paras[] = 'Recorded reason for leaving: ' . str_replace('_', ' ', $reason) . '.';
        }

        return ['TRANSFER CERTIFICATE', $paras, 'We wish ' . $name . ' every success in ' . $p['possessive'] . ' continuing studies.'];
    }

    private function completion(
        Student $student,
        ?School $school,
        string $name,
        ?string $level,
        ?StudentEnrollment $current,
        array $p
    ): array {
        $year   = $current?->academic_year;
        $paras  = [];

        $paras[] = $level
            ? "This is to certify that {$name} (student ID {$student->uid}) has completed {$level} at {$school?->name}" . ($year ? " in the {$year} academic year." : '.')
            : "This is to certify that {$name} (student ID {$student->uid}) has completed {$p['possessive']} studies at {$school?->name}.";

        $cumulative = $this->transcripts->build($student)['cumulative'] ?? null;

        if ($cumulative && $cumulative['average'] !== null) {
            $paras[] = "Across {$p['possessive']} published results {$p['subject']} achieved a cumulative average of "
                . $cumulative['average'] . '%'
                . ($cumulative['letter'] ? " (grade {$cumulative['letter']})" : '')
                . '. A full academic transcript is available from the school on request.';
        }

        return ['CERTIFICATE OF COMPLETION', $paras, 'We congratulate ' . $name . ' and wish ' . $p['object'] . ' well in what comes next.'];
    }

    private function character(
        Student $student,
        ?School $school,
        string $name,
        ?StudentEnrollment $first,
        ?StudentEnrollment $current,
        array $p,
        array $options
    ): array {
        $from = $first?->enrolled_on?->format('j F Y');
        $to   = $current?->ended_on?->format('j F Y')
            ?? ($current?->is_current ? null : now()->format('j F Y'));

        $paras = [];

        // "was a student since X" is wrong for someone still on roll.
        $stillEnrolled = $to === null;

        $period = match (true) {
            $from !== null && $to !== null => " from {$from} to {$to}",
            $from !== null                 => " since {$from}",
            default                        => '',
        };

        $verb = $stillEnrolled ? 'has been a student of' : 'was a student of';

        $paras[] = "This is to certify that {$name} (student ID {$student->uid}) {$verb} {$school?->name}{$period}.";

        $note = trim((string) ($options['body_note'] ?? ''));
        $paras[] = $note !== '' ? $note : $this->suggestedConductNote($student);

        return ['TO WHOM IT MAY CONCERN', $paras, 'This reference is issued at the request of the student and their family.'];
    }

    private function bonafide(
        Student $student,
        ?School $school,
        string $name,
        ?string $level,
        ?StudentEnrollment $current,
        array $p
    ): array {
        $year = $current?->academic_year;

        $paras = [];
        $paras[] = $level
            ? "This is to certify that {$name} (student ID {$student->uid}) is a currently enrolled student of {$school?->name}, studying in {$level}" . ($year ? " for the {$year} academic year." : '.')
            : "This is to certify that {$name} (student ID {$student->uid}) is a currently enrolled student of {$school?->name}.";

        $enrolled = $current?->enrolled_on?->format('j F Y') ?? $student->enrollment_date?->format('j F Y');
        if ($enrolled) {
            $Subject = ucfirst($p['subject']);

            $paras[] = "{$Subject} has been enrolled since {$enrolled} and is expected to continue {$p['possessive']} studies with us.";
        }

        return ['TO WHOM IT MAY CONCERN', $paras, 'This letter is issued at the request of the student and their family for official use.'];
    }

    private function custom(array $options): array
    {
        $note = trim((string) ($options['body_note'] ?? ''));

        return [
            'TO WHOM IT MAY CONCERN',
            [$note !== '' ? $note : 'This letter is issued at the request of the student and their family.'],
            'Yours faithfully,',
        ];
    }

    // ── Shared pieces ───────────────────────────────────────────

    private function facts(Student $student, $enrollments, ?StudentEnrollment $current, ?StudentEnrollment $first): array
    {
        // A year can exist as published results without an enrolment record
        // (data entered before the lifecycle spine existed). The transcript
        // shows those years, so the letter's fact table must agree with it.
        $markYears = StudentGrade::withoutGlobalScopes()
            ->where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->where('status', 'published')
            ->with('term:id,academic_year')
            ->get()
            ->pluck('term.academic_year');

        $years = $enrollments->pluck('academic_year')
            ->merge($markYears)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $facts = [
            ['label' => 'Full name', 'value' => $student->full_name],
            ['label' => 'Student ID', 'value' => $student->uid],
        ];

        if ($student->gender) {
            $facts[] = ['label' => 'Gender', 'value' => ucfirst($student->gender)];
        }
        if ($student->date_of_birth) {
            $facts[] = ['label' => 'Date of birth', 'value' => $student->date_of_birth->format('j F Y')];
        }
        if ($first?->enrolled_on) {
            $facts[] = ['label' => 'First enrolled', 'value' => $first->enrolled_on->format('j F Y')];
        }
        if ($level = $this->levelLabel($current)) {
            $facts[] = ['label' => 'Current / last class', 'value' => $level];
        }
        if ($years->isNotEmpty()) {
            $facts[] = ['label' => 'Academic years', 'value' => $years->implode(', ')];
        }
        if ($student->status) {
            $facts[] = ['label' => 'Status', 'value' => ucfirst(str_replace('_', ' ', $student->status))];
        }

        return $facts;
    }

    private function levelLabel(?StudentEnrollment $enrollment): ?string
    {
        if (! $enrollment) {
            return null;
        }

        if ($enrollment->cohort) {
            return trim($enrollment->cohort->grade . ' ' . $enrollment->cohort->section);
        }

        return null;
    }

    private function pronouns(?string $gender): array
    {
        return match (strtolower((string) $gender)) {
            'male'   => ['subject' => 'he',  'object' => 'him', 'possessive' => 'his',   'reflexive' => 'himself'],
            'female' => ['subject' => 'she', 'object' => 'her', 'possessive' => 'her',   'reflexive' => 'herself'],
            default  => ['subject' => 'they', 'object' => 'them', 'possessive' => 'their', 'reflexive' => 'themselves'],
        };
    }
}
