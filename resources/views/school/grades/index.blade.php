@extends('layouts.school')

@section('title', 'Grades')
@section('heading', 'Grades')
@section('subheading', $class && $term ? $class->display_name . ' · ' . $term->name : 'Select a class and term to manage grades')

@section('content')

{{-- Filter / selector --}}
<form method="GET" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
            <select name="class_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
                <option value="">Select class…</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" {{ ($filters['class_id'] ?? '') == $c->id ? 'selected' : '' }}>{{ $c->display_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
            <select name="term_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
                <option value="">Select term…</option>
                @foreach($terms as $t)
                    <option value="{{ $t->id }}" {{ ($filters['term_id'] ?? '') == $t->id ? 'selected' : '' }}>{{ $t->name }} ({{ $t->academic_year }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Load</button>
        </div>
        @if($class && $term)
            <div class="flex justify-end">
                <a href="{{ route('school.grades.gradebook', ['class_id' => $class->id, 'term_id' => $term->id]) }}"
                   class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M3 14h18M10 3v18M14 3v18"/></svg>
                    Gradebook
                </a>
            </div>
        @endif
    </div>
</form>

@if($terms->isEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <p class="font-medium text-gray-500 mb-1">No terms set up yet</p>
        @if(auth()->user()->isSchoolAdmin())
            <a href="{{ route('school.grades.terms') }}" class="text-indigo-500 hover:text-indigo-600 text-sm mt-2 inline-block">Manage terms &rarr;</a>
        @endif
    </div>

@elseif($class && $term)

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    @if(empty($matrix))
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
            <p>No subjects assigned to {{ $class->display_name }}.</p>
            <a href="{{ route('school.subjects.index') }}" class="text-indigo-500 hover:text-indigo-600 text-sm mt-1 inline-block">Manage subjects &rarr;</a>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-left">
                        <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Subject</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-center">CA</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-center">Midterm</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-center">Final</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($matrix as $row)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ $row['subject']->name }}</p>
                                @if($row['class_subject']->teacher)
                                    <p class="text-xs text-gray-400">{{ $row['class_subject']->teacher->name }}</p>
                                @endif
                            </td>
                            @foreach(['ca', 'midterm', 'final'] as $type)
                                @php
                                    $td = $row['types'][$type];
                                    $colors = [
                                        'not_started' => 'bg-gray-100 text-gray-400',
                                        'draft'       => 'bg-amber-50 text-amber-600',
                                        'partial'     => 'bg-blue-50 text-blue-600',
                                        'published'   => 'bg-green-50 text-green-600',
                                    ];
                                    $label = match($td['status']) {
                                        'not_started' => '—',
                                        'published'   => 'Published',
                                        default       => $td['entered'] . '/' . $td['total'],
                                    };
                                    $canEdit    = $td['status'] !== 'published' && (auth()->user()->isSchoolAdmin() || auth()->user()->isTeacher());
                                    $canPublish = in_array($td['status'], ['draft','partial']) && auth()->user()->isSchoolAdmin();
                                @endphp
                                <td class="px-5 py-3 text-center">
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $colors[$td['status']] }}">{{ $label }}</span>
                                    <div class="flex justify-center gap-2 mt-1">
                                        @if($canEdit)
                                            <a href="{{ route('school.grades.enter', ['class_id' => $class->id, 'subject_id' => $row['subject']->id, 'term_id' => $term->id, 'assessment_type' => $type]) }}"
                                               class="text-xs text-indigo-500 hover:text-indigo-700">{{ $td['status'] === 'not_started' ? 'Enter' : 'Edit' }}</a>
                                        @endif
                                        @if($canPublish)
                                            <form method="POST" action="{{ route('school.grades.publish') }}">
                                                @csrf
                                                <input type="hidden" name="class_id" value="{{ $class->id }}">
                                                <input type="hidden" name="subject_id" value="{{ $row['subject']->id }}">
                                                <input type="hidden" name="term_id" value="{{ $term->id }}">
                                                <input type="hidden" name="assessment_type" value="{{ $type }}">
                                                <button type="submit" class="text-xs text-green-600 hover:text-green-800">Publish</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif

@endsection
