@extends('layouts.school')

@section('title', 'Report Cards')
@section('heading', 'Report Cards')
@section('subheading', 'Select a class and term to view or print student report cards')

@section('content')
{{-- Filters --}}
<form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
    <select name="class_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-white focus:outline-none focus:border-indigo-400">
        <option value="">Select Class…</option>
        @foreach($classes as $cls)
            <option value="{{ $cls->id }}" {{ ($filters['class_id'] ?? '') == $cls->id ? 'selected' : '' }}>{{ $cls->display_name }}</option>
        @endforeach
    </select>
    <select name="term_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-white focus:outline-none focus:border-indigo-400">
        <option value="">Select Term…</option>
        @foreach($terms as $t)
            <option value="{{ $t->id }}" {{ ($filters['term_id'] ?? '') == $t->id ? 'selected' : '' }}>{{ $t->name }} ({{ $t->academic_year }})</option>
        @endforeach
    </select>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">View</button>
    @if(!empty($filters['class_id']) || !empty($filters['term_id']))
        <a href="{{ route('school.report-cards.index') }}" class="text-sm text-gray-400 hover:text-gray-600">Clear</a>
    @endif
</form>

@if($class && $term)
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-700">{{ $class->display_name }} — {{ $term->name }} ({{ $term->academic_year }})</h2>
        <span class="text-xs text-gray-400">{{ $students->count() }} active student{{ $students->count() === 1 ? '' : 's' }}</span>
    </div>

    @if($students->isEmpty())
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
            <p>No active students in this class.</p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left bg-gray-50">
                            <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">#</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Student</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">UID</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($students as $i => $student)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-5 py-3 text-gray-400 text-xs">{{ $i + 1 }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($student->photo_url)
                                            <img src="{{ $student->photo_url }}" class="w-8 h-8 rounded-full object-cover" alt="">
                                        @else
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-500">
                                                {{ strtoupper(substr($student->first_name,0,1)) }}{{ strtoupper(substr($student->last_name,0,1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $student->full_name }}</p>
                                            @if($student->first_name_am)
                                                <p class="text-xs text-gray-400">{{ $student->full_name_am }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $student->uid }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('school.report-cards.show', $student) . '?term_id=' . $term->id }}"
                                       class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                        View Report Card
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@else
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <p>Select a class and term to view report cards</p>
    </div>
@endif
@endsection
