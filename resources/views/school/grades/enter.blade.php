@extends('layouts.school')

@section('title', 'Enter Grades')
@section('heading', $subject->name . ' — ' . $assessmentLabel)
@section('subheading', $class->display_name . ' · ' . $term->name)

@section('content')

@if($isLocked)
    <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg text-sm">
        These grades have been published and cannot be modified.
    </div>
@endif

@if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
        {{ $errors->first() }}
    </div>
@endif

<form method="POST" action="{{ route('school.grades.save') }}">
    @csrf
    <input type="hidden" name="class_id" value="{{ $class->id }}">
    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
    <input type="hidden" name="term_id" value="{{ $term->id }}">
    <input type="hidden" name="assessment_type" value="{{ $assessmentType }}">

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <div>
                <p class="font-medium text-gray-900">{{ $subject->name }} · {{ $assessmentLabel }}</p>
                <p class="text-xs text-gray-400">{{ $students->count() }} students · Max score: 100</p>
            </div>
            @if($config)
                @php
                    $weights = ['ca' => $config->ca_weight, 'midterm' => $config->midterm_weight, 'final' => $config->final_weight];
                @endphp
                <p class="text-xs text-gray-400">Weight: {{ $weights[$assessmentType] }}%</p>
            @endif
        </div>

        <div class="divide-y divide-gray-50">
            @forelse($students as $i => $student)
                @php $grade = $existing[$student->id] ?? null; @endphp
                <div class="px-5 py-3 flex items-center gap-4">
                    <input type="hidden" name="grades[{{ $i }}][student_id]" value="{{ $student->id }}">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-500 flex-shrink-0">
                            {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 truncate">{{ $student->full_name }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $student->uid }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <div class="flex items-center gap-1">
                            <input type="number" name="grades[{{ $i }}][score]"
                                   value="{{ $grade ? $grade->score : '' }}"
                                   min="0" max="100" step="0.5"
                                   placeholder="Score"
                                   {{ $isLocked ? 'disabled' : '' }}
                                   class="w-24 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-center focus:outline-none focus:border-indigo-400 {{ $isLocked ? 'bg-gray-50 text-gray-400' : '' }}"
                                   required>
                            <span class="text-gray-400 text-sm">/</span>
                            <input type="number" name="grades[{{ $i }}][max_score]"
                                   value="{{ $grade ? $grade->max_score : 100 }}"
                                   min="1" max="100" step="0.5"
                                   {{ $isLocked ? 'disabled' : '' }}
                                   class="w-20 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-center focus:outline-none focus:border-indigo-400 {{ $isLocked ? 'bg-gray-50 text-gray-400' : '' }}"
                                   required>
                        </div>
                        @if($grade)
                            @php $statusColors = ['draft'=>'bg-amber-50 text-amber-600','published'=>'bg-green-50 text-green-600','submitted'=>'bg-blue-50 text-blue-600']; @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $statusColors[$grade->status] ?? 'bg-gray-100 text-gray-400' }} capitalize">{{ $grade->status }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center text-gray-400">No active students in this class.</div>
            @endforelse
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        @if(!$isLocked)
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">Save Grades</button>
        @endif
        <a href="{{ route('school.grades.index', ['class_id' => $class->id, 'term_id' => $term->id]) }}"
           class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
    </div>
</form>

@endsection
