<?php

namespace App\Http\Controllers\School;

use App\Models\AdmissionApplication;
use App\Models\Programme;
use App\Models\SchoolClass;
use App\Services\AdmissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The admissions pipeline: enquiry → applied → screening → offered →
 * accepted → enrolled, with rejected and withdrawn kept on file.
 */
class AdmissionController extends BaseSchoolController
{
    public function __construct(private AdmissionService $admissions)
    {
    }

    /** Pipe se list with a status filter and headline counts. */
    public function index(Request $request)
    {
        $school = $this->currentSchool();

        $query = AdmissionApplication::query()
            ->where('school_id', $school->id)
            ->with(['programme:id,name', 'cohort:id,grade,section', 'student:id,uid,first_name,last_name']);

        if ($status = $request->get('status')) {
            if ($status === 'open') {
                $query->whereNotIn('status', AdmissionApplication::CLOSED);
            } else {
                $query->where('status', $status);
            }
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('guardian_phone', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $applications = $query->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($a) => [
                'id'            => $a->id,
                'reference'     => $a->reference,
                'name'          => $a->fullName(),
                'gender'        => $a->gender,
                'status'        => $a->status,
                'status_label'  => $a->statusLabel(),
                'source'        => $a->source,
                'source_label'  => $a->sourceLabel(),
                'guardian_name' => $a->guardian_name,
                'guardian_phone'=> $a->guardian_phone ?? $a->phone,
                'programme'     => $a->programme?->name,
                'class_name'    => $a->cohort ? trim($a->cohort->grade . ' ' . $a->cohort->section) : null,
                'intended_level'=> $a->intended_level,
                'student_id'    => $a->student_id,
                'student_uid'   => $a->student?->uid,
                'age_days'      => $a->ageInDays(),
                'created_at'    => $a->created_at?->format('d M Y'),
            ]);

        $counts = AdmissionApplication::query()
            ->where('school_id', $school->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('School/Admissions/Index', [
            'applications' => $applications,
            'counts'       => $counts,
            'statuses'     => collect(AdmissionApplication::STATUSES)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
            'filters'      => $request->only('status', 'search'),
            'is_admin'     => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    public function create()
    {
        $this->requireAdmin();

        return Inertia::render('School/Admissions/Form', [
            'application' => null,
            'reference'   => $this->admissions->nextReference($this->currentSchool()),
            'options'     => $this->formOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $data = $this->validated($request);

        AdmissionApplication::create(array_merge($data, [
            'school_id'  => $this->schoolId(),
            'reference'  => $this->admissions->nextReference($this->currentSchool()),
            'handled_by' => auth()->id(),
        ]));

        return redirect()->route('school.admissions.index')
            ->with('success', 'Application recorded.');
    }

    public function show(AdmissionApplication $application)
    {
        $application->load(['programme:id,name', 'cohort:id,grade,section', 'student', 'handler:id,name']);

        return Inertia::render('School/Admissions/Show', [
            'application' => [
                'id'            => $application->id,
                'reference'     => $application->reference,
                'first_name'    => $application->first_name,
                'last_name'     => $application->last_name,
                'first_name_am' => $application->first_name_am,
                'last_name_am'  => $application->last_name_am,
                'full_name'     => $application->fullName(),
                'gender'        => $application->gender,
                'date_of_birth' => $application->date_of_birth?->format('Y-m-d'),
                'phone'         => $application->phone,
                'email'         => $application->email,
                'address_line'  => $application->address_line,
                'guardian_name' => $application->guardian_name,
                'guardian_phone'=> $application->guardian_phone,
                'guardian_email'=> $application->guardian_email,
                'guardian_relationship' => $application->guardian_relationship,
                'previous_school' => $application->previous_school,
                'previous_grade'  => $application->previous_grade,
                'programme_id'  => $application->programme_id,
                'programme'     => $application->programme?->name,
                'class_id'      => $application->class_id,
                'class_name'    => $application->cohort ? trim($application->cohort->grade . ' ' . $application->cohort->section) : null,
                'academic_year' => $application->academic_year,
                'intended_level'=> $application->intended_level,
                'status'        => $application->status,
                'status_label'  => $application->statusLabel(),
                'source'        => $application->source,
                'source_label'  => $application->sourceLabel(),
                'notes'         => $application->notes,
                'decision_note' => $application->decision_note,
                'follow_up_on'  => $application->follow_up_on?->format('Y-m-d'),
                'handled_by'    => $application->handler?->name,
                'decided_at'    => $application->decided_at?->format('d M Y H:i'),
                'converted_at'  => $application->converted_at?->format('d M Y H:i'),
                'student'       => $application->student ? [
                    'id'   => $application->student->id,
                    'uid'  => $application->student->uid,
                    'name' => $application->student->full_name,
                ] : null,
                'created_at'    => $application->created_at?->format('d M Y H:i'),
            ],
            'options' => $this->formOptions(),
            'statuses' => collect(AdmissionApplication::STATUSES)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
            'is_admin' => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    public function update(Request $request, AdmissionApplication $application)
    {
        $this->requireAdmin();

        $data = $this->validated($request, $application->id);

        $application->update($data);

        return back()->with('success', 'Application updated.');
    }

    /** Move an application along the pipeline. */
    public function changeStatus(Request $request, AdmissionApplication $application)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'status'        => ['required', Rule::in(array_keys(AdmissionApplication::STATUSES))],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($application->status === 'converted') {
            return back()->withErrors([
                'status' => 'This application is already a student. Use their student record instead.',
            ]);
        }

        $application->update([
            'status'        => $data['status'],
            'decision_note' => $data['decision_note'] ?? $application->decision_note,
            'decided_at'    => now(),
            'handled_by'    => auth()->id(),
        ]);

        return back()->with('success', 'Moved to ' . AdmissionApplication::STATUSES[$data['status']] . '.');
    }

    /** Enrol: turn the application into a student. */
    public function convert(Request $request, AdmissionApplication $application)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $data = $request->validate([
            'class_id' => ['nullable', 'integer', Rule::exists('classes', 'id')
                ->where('school_id', $this->schoolId())],
            'note'     => ['nullable', 'string', 'max:1000'],
        ]);

        $student = $this->admissions->convert(
            $application,
            !empty($data['class_id']) ? (int) $data['class_id'] : null,
            $data['note'] ?? null,
        );

        return redirect()->route('school.students.show', $student->id)
            ->with('success', "Enrolled as {$student->uid}. Add guardians and documents from their record.");
    }

    public function destroy(AdmissionApplication $application)
    {
        $this->requireAdmin();

        if ($application->student_id) {
            return back()->withErrors([
                'application' => 'This application became a student — it cannot be deleted.',
            ]);
        }

        $application->delete();

        return redirect()->route('school.admissions.index')->with('success', 'Application removed.');
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function formOptions(): array
    {
        return [
            'programmes' => Programme::query()
                ->where('school_id', $this->schoolId())
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name]),
            'classes' => SchoolClass::query()
                ->where('is_active', true)
                ->orderBy('level_order')
                ->orderBy('grade')
                ->orderBy('section')
                ->get()
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->lifecycle_label]),
            'sources' => collect(AdmissionApplication::SOURCES)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'first_name_am' => ['nullable', 'string', 'max:100'],
            'last_name_am'  => ['nullable', 'string', 'max:100'],
            'gender'        => ['nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:120'],
            'address_line'  => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'guardian_phone'=> ['nullable', 'string', 'max:30'],
            'guardian_email'=> ['nullable', 'email', 'max:120'],
            'guardian_relationship' => ['nullable', 'string', 'max:40'],
            'previous_school' => ['nullable', 'string', 'max:150'],
            'previous_grade'  => ['nullable', 'string', 'max:40'],
            'programme_id'  => ['nullable', 'integer', Rule::exists('programmes', 'id')
                ->where('school_id', $this->schoolId())],
            'class_id'      => ['nullable', 'integer', Rule::exists('classes', 'id')
                ->where('school_id', $this->schoolId())],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'intended_level'=> ['nullable', 'string', 'max:40'],
            'status'        => ['nullable', Rule::in(array_keys(AdmissionApplication::STATUSES))],
            'source'        => ['nullable', Rule::in(array_keys(AdmissionApplication::SOURCES))],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'follow_up_on'  => ['nullable', 'date'],
        ]);
    }
}
