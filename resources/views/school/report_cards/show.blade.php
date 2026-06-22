@extends('layouts.school')

@section('title', $student->full_name . ' — Report Card')
@section('heading', 'Report Card')
@section('subheading', $student->full_name . ' · ' . $term->name . ' (' . $term->academic_year . ')')

@section('content')
{{-- Nav --}}
<div class="flex items-center justify-between mb-6 print:hidden">
    <a href="{{ url()->previous() }}" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-gray-600 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to class list
    </a>
    <button onclick="window.print()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Print
    </button>
</div>

{{-- Report Card body (print-styled) --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-8 max-w-3xl mx-auto print:shadow-none print:border-none print:rounded-none print:p-4">

    {{-- Header --}}
    <div class="text-center mb-6 pb-4 border-b border-gray-200">
        <p class="text-lg font-bold text-gray-800">{{ $school->name }}</p>
        @if($school->address ?? false)
            <p class="text-xs text-gray-400 mt-0.5">{{ $school->address }}</p>
        @endif
        <h1 class="mt-3 text-xl font-extrabold tracking-wide text-gray-900 uppercase">Student Report Card</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $term->name }} — {{ $term->academic_year }}</p>
    </div>

    {{-- Student info --}}
    <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
        <div>
            <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Student Name</p>
            <p class="font-medium text-gray-900">{{ $student->full_name }}</p>
            @if($student->first_name_am)
                <p class="text-xs text-gray-500">{{ $student->full_name_am }}</p>
            @endif
        </div>
        <div>
            <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Student ID</p>
            <p class="font-mono font-medium text-gray-700">{{ $student->uid }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Class</p>
            <p class="text-gray-700">{{ $student->schoolClass?->display_name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Gender</p>
            <p class="text-gray-700 capitalize">{{ $student->gender }}</p>
        </div>
        @if($student->date_of_birth)
        <div>
            <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Date of Birth</p>
            <p class="text-gray-700">{{ $student->date_of_birth->format('M d, Y') }}</p>
        </div>
        @endif
    </div>

    {{-- Grades table --}}
    <table class="w-full text-sm mb-6 border border-gray-200 rounded-lg overflow-hidden">
        <thead class="bg-gray-50">
            <tr class="text-left">
                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Subject</th>
                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase text-center">CA</th>
                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase text-center">Midterm</th>
                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase text-center">Final</th>
                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase text-center">Total %</th>
                <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase text-center">Grade</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($subjectRows as $row)
                <tr class="{{ $loop->even ? 'bg-gray-50/50' : '' }}">
                    <td class="px-4 py-2 text-gray-800 font-medium">{{ $row['subject']->name }}</td>
                    <td class="px-4 py-2 text-center text-gray-600">
                        @if($row['ca'])
                            {{ number_format((float)$row['ca']->score,1) }}/{{ number_format((float)$row['ca']->max_score,0) }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-center text-gray-600">
                        @if($row['midterm'])
                            {{ number_format((float)$row['midterm']->score,1) }}/{{ number_format((float)$row['midterm']->max_score,0) }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-center text-gray-600">
                        @if($row['final'])
                            {{ number_format((float)$row['final']->score,1) }}/{{ number_format((float)$row['final']->max_score,0) }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-center font-semibold {{ $row['total'] !== null ? ($row['total'] >= 50 ? 'text-green-700' : 'text-red-600') : 'text-gray-300' }}">
                        {{ $row['total'] !== null ? number_format($row['total'],1).'%' : '—' }}
                    </td>
                    <td class="px-4 py-2 text-center">
                        @if($row['letter'])
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-indigo-50 text-indigo-700">{{ $row['letter'] }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">No published grades for this term yet.</td>
                </tr>
            @endforelse
        </tbody>
        @if($termAvg !== null)
        <tfoot class="bg-indigo-50 border-t-2 border-indigo-200">
            <tr>
                <td colspan="4" class="px-4 py-2 text-sm font-semibold text-indigo-800 text-right">Term Average</td>
                <td class="px-4 py-2 text-center text-sm font-bold text-indigo-700">{{ number_format($termAvg, 1) }}%</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- Summary row --}}
    @if($termAvg !== null)
    <div class="flex flex-wrap gap-4 mb-6 text-sm">
        <div class="flex-1 min-w-[100px] bg-indigo-50 rounded-lg p-3 text-center">
            <p class="text-xs text-indigo-400 uppercase font-semibold">Term Average</p>
            <p class="text-2xl font-bold text-indigo-700 mt-0.5">{{ number_format($termAvg, 1) }}%</p>
        </div>
        @if($rank !== null)
        <div class="flex-1 min-w-[100px] bg-emerald-50 rounded-lg p-3 text-center">
            <p class="text-xs text-emerald-400 uppercase font-semibold">Class Rank</p>
            <p class="text-2xl font-bold text-emerald-700 mt-0.5">{{ $rank }}<span class="text-sm font-normal text-emerald-500"> / {{ $classSize }}</span></p>
        </div>
        @endif
    </div>
    @endif

    {{-- Signature lines --}}
    <div class="mt-8 pt-4 border-t border-gray-200 grid grid-cols-3 gap-8 text-xs text-gray-500">
        <div class="text-center">
            <div class="border-b border-gray-300 mb-1 h-8"></div>
            Class Teacher
        </div>
        <div class="text-center">
            <div class="border-b border-gray-300 mb-1 h-8"></div>
            School Principal
        </div>
        <div class="text-center">
            <div class="border-b border-gray-300 mb-1 h-8"></div>
            Parent / Guardian
        </div>
    </div>

    <p class="mt-4 text-center text-xs text-gray-300">Generated {{ now()->format('M d, Y') }} · EduID</p>
</div>

<style>
@media print {
    .print\:hidden { display: none !important; }
    body { background: white; }
}
</style>
@endsection
