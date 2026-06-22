@extends('layouts.school')

@section('title', 'Gradebook')
@section('heading', 'Gradebook')
@section('subheading', $class->display_name . ' · ' . $term->name)

@section('content')

<div class="flex items-center justify-between mb-4">
    <a href="{{ route('school.grades.index', ['class_id' => $class->id, 'term_id' => $term->id]) }}"
       class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to grade entry</a>
    <p class="text-sm text-gray-400">{{ count($rows) }} students · {{ $classSubjects->count() }} subjects</p>
</div>

@if(empty($rows))
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
        <p>No active students in this class.</p>
    </div>
@else
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50 text-left">
                    <th class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase sticky left-0 bg-gray-50 z-10">Rank</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase sticky left-12 bg-gray-50 z-10 min-w-[160px]">Student</th>
                    @foreach($classSubjects as $cs)
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase text-center min-w-[80px]">{{ $cs->subject->code ?? Str::limit($cs->subject->name, 8) }}</th>
                    @endforeach
                    <th class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase text-center bg-indigo-50 sticky right-0">Average</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($rows as $row)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-2.5 text-gray-400 text-xs sticky left-0 bg-white">
                            {{ $row['rank'] !== null ? '#' . $row['rank'] : '—' }}
                        </td>
                        <td class="px-4 py-2.5 sticky left-12 bg-white min-w-[160px]">
                            <p class="font-medium text-gray-900">{{ $row['student']->full_name }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $row['student']->uid }}</p>
                        </td>
                        @foreach($classSubjects as $cs)
                            @php $st = $row['subject_totals'][$cs->subject_id] ?? null; @endphp
                            <td class="px-3 py-2.5 text-center">
                                @if($st && $st['total'] !== null)
                                    <span class="font-medium {{ $st['total'] >= 50 ? 'text-gray-900' : 'text-red-600' }}">
                                        {{ number_format($st['total'], 1) }}
                                    </span>
                                    @if(!$st['published'])
                                        <span class="block text-xs text-amber-500">draft</span>
                                    @endif
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-2.5 text-center bg-indigo-50 sticky right-0 font-semibold">
                            @if($row['term_avg'] !== null)
                                <span class="{{ $row['term_avg'] >= 50 ? 'text-indigo-700' : 'text-red-600' }}">
                                    {{ number_format($row['term_avg'], 1) }}
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 flex gap-4 text-xs text-gray-400">
    <span class="flex items-center gap-1"><span class="text-amber-500 font-medium">draft</span> = grades saved but not yet published</span>
    <span class="flex items-center gap-1"><span class="text-red-600 font-medium">red score</span> = below 50%</span>
    <span class="flex items-center gap-1"><span class="text-gray-300">—</span> = not all assessments entered</span>
</div>
@endif

@endsection
