<?php

namespace App\Http\Controllers\School;

use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClassController extends BaseSchoolController
{
    public function index()
    {
        $classes = SchoolClass::withCount('students')
            ->with('programme:id,name,code')
            ->orderBy('level_order')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        return Inertia::render('School/Classes/Index', [
            'classes' => $classes->map(fn ($c) => [
                'id'             => $c->id,
                'grade'          => $c->grade,
                'section'        => $c->section,
                'display_name'   => $c->display_name,
                'academic_year'  => $c->academic_year,
                'students_count' => $c->students_count,
                'is_active'      => (bool) $c->is_active,
                'programme'      => $c->programme?->name,
                'cohort_kind'    => $c->cohort_kind,
                'shift'          => $c->shift,
                'capacity'       => $c->capacity,
                'seats_left'     => $c->seatsRemaining(),
            ]),
            'is_admin' => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    public function create()
    {
        $this->requireAdmin();

        return Inertia::render('School/Classes/Form', [
            'schoolClass'   => null,
            'academic_year' => $this->currentSchool()->academic_year,
            'programmes'    => $this->programmeOptions(),
            'shifts'        => collect(SchoolClass::SHIFTS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'kinds'         => collect(SchoolClass::KINDS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
        ]);
    }

    /** Programmes offered by the current school, for the class form. */
    private function programmeOptions()
    {
        return \App\Models\Programme::query()
            ->where('school_id', $this->schoolId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name . ' (' . $p->code . ')']);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate($this->rules(), $this->messages());
        $validated['is_active'] = true;
        $validated['level_order'] = $validated['level_order'] ?? 0;
        $validated = $this->normalise($validated);

        SchoolClass::create($validated);

        return redirect()->route('school.classes.index')->with('success', 'Class created successfully.');
    }

    /**
     * Fill in the cohort vocabulary. A primary/secondary school never has to
     * think about it: the class lands in the school's programme (or its
     * "General" fallback) as a full-day grade section.
     */
    private function normalise(array $data): array
    {
        if (empty($data['programme_id'])) {
            $data['programme_id'] = \App\Models\Programme::query()
                ->where('school_id', $this->schoolId())
                ->where('code', 'GEN')
                ->value('id')
                ?? \App\Models\Programme::query()
                    ->where('school_id', $this->schoolId())
                    ->orderBy('id')
                    ->value('id');
        }

        $data['cohort_kind'] = $data['cohort_kind'] ?? 'grade_section';
        $data['shift'] = $data['shift'] ?? 'full_day';

        // Derive the level number from the grade label when it was not given.
        if (($data['level_number'] ?? null) === null && !empty($data['grade'])) {
            if (preg_match('/(\d+)/', (string) $data['grade'], $m)) {
                $data['level_number'] = (int) $m[1];
            } elseif (stripos((string) $data['grade'], 'kg') !== false
                || stripos((string) $data['grade'], 'nursery') !== false) {
                $data['level_number'] = 0;
            }
        }

        return $data;
    }

    public function edit(SchoolClass $class)
    {
        $this->requireAdmin();

        return Inertia::render('School/Classes/Form', [
            'schoolClass' => [
                'id'            => $class->id,
                'programme_id'  => $class->programme_id,
                'grade'         => $class->grade,
                'section'       => $class->section,
                'academic_year' => $class->academic_year,
                'cohort_kind'   => $class->cohort_kind ?? 'grade_section',
                'level_number'  => $class->level_number,
                'shift'         => $class->shift ?? 'full_day',
                'capacity'      => $class->capacity,
                'start_date'    => $class->start_date?->format('Y-m-d'),
                'end_date'      => $class->end_date?->format('Y-m-d'),
                'level_order'   => $class->level_order,
                'is_active'     => (bool) $class->is_active,
                'display_name'  => $class->display_name,
            ],
            'academic_year' => $class->academic_year,
            'programmes'    => $this->programmeOptions(),
            'shifts'        => collect(SchoolClass::SHIFTS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'kinds'         => collect(SchoolClass::KINDS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
        ]);
    }

    public function update(Request $request, SchoolClass $class)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate($this->rules($class->id) + [
            'is_active' => 'boolean',
        ], $this->messages());
        $validated['is_active'] = $request->boolean('is_active');
        $validated['level_order'] = $validated['level_order'] ?? 0;
        $validated = $this->normalise($validated);

        $class->update($validated);

        return redirect()->route('school.classes.index')->with('success', 'Class updated.');
    }

    /**
     * Validation rules. A class is uniquely identified by grade + section +
     * academic year within a school, enforced here (scoped to current school).
     */
    private function rules(?int $ignoreId = null): array
    {
        $unique = Rule::unique('classes')
            ->where('school_id', $this->schoolId())
            ->where('section', request('section'))
            ->where('academic_year', request('academic_year'))
            ->ignore($ignoreId);

        return [
            'programme_id'  => ['nullable', 'integer', Rule::exists('programmes', 'id')
                ->where('school_id', $this->schoolId())],
            'grade'         => ['required', 'string', 'max:50', $unique],
            'section'       => 'required|string|max:10',
            'academic_year' => 'required|string|max:20',
            'cohort_kind'   => ['nullable', Rule::in(array_keys(SchoolClass::KINDS))],
            'level_number'  => 'nullable|integer|min:0|max:20',
            'shift'         => ['nullable', Rule::in(array_keys(SchoolClass::SHIFTS))],
            'capacity'      => 'nullable|integer|min:1|max:1000',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'level_order'   => 'nullable|integer|min:0',
        ];
    }

    /**
     * Clear, unambiguous messages. A class is unique by grade + section +
     * academic year, so the default "grade has already been taken" is
     * misleading — it makes admins think they can't reuse a grade for another
     * section (which they absolutely can). Spell out the real conflict.
     */
    private function messages(): array
    {
        return [
            'grade.unique' => 'A class for this grade and section already exists for '
                .(request('academic_year') ?: 'this academic year')
                .'. To add another section, change the Section field (e.g. A → B).',
        ];
    }
}
