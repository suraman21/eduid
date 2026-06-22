@extends('layouts.school')

@section('title', 'Attendance Session')
@section('heading', $session->schoolClass?->display_name ?? 'Attendance')
@section('subheading', $session->session_date->format('M d, Y') . ' · ' . ucfirst(str_replace('_', ' ', $session->session_type)))

@section('content')
@php
    $present  = $session->records->where('status', 'present')->count();
    $late     = $session->records->where('status', 'late')->count();
    $excused  = $session->records->where('status', 'excused')->count();
    $absent   = $session->records->where('status', 'absent')->count();
    $total    = $session->records->count();
@endphp

<div class="max-w-3xl space-y-6">
    {{-- Summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-2xl font-semibold text-green-600">{{ $present }}</p>
            <p class="text-xs text-gray-400 uppercase tracking-wider">Present</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-2xl font-semibold text-amber-600">{{ $late }}</p>
            <p class="text-xs text-gray-400 uppercase tracking-wider">Late</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-2xl font-semibold text-blue-600">{{ $excused }}</p>
            <p class="text-xs text-gray-400 uppercase tracking-wider">Excused</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-2xl font-semibold text-red-600">{{ $absent }}</p>
            <p class="text-xs text-gray-400 uppercase tracking-wider">Absent</p>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">Recorded by {{ $session->teacher?->name ?? '—' }}</p>
        <a href="{{ route('school.attendance.create', ['class_id' => $session->class_id, 'session_date' => $session->session_date->toDateString(), 'session_type' => $session->session_type]) }}"
           class="text-sm text-indigo-500 hover:text-indigo-600 font-medium">Edit attendance</a>
    </div>

    {{-- Records --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left bg-gray-50">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Student</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($session->records->sortBy('student.first_name') as $record)
                    @php $colors = ['present'=>'bg-green-50 text-green-600','late'=>'bg-amber-50 text-amber-600','excused'=>'bg-blue-50 text-blue-600','absent'=>'bg-red-50 text-red-600']; @endphp
                    <tr>
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $record->student?->full_name ?? '—' }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $record->student?->uid }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $colors[$record->status] ?? 'bg-gray-100 text-gray-500' }} capitalize">{{ $record->status }}</span>
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ $record->note ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <a href="{{ route('school.attendance.index') }}" class="inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Back to Attendance</a>
</div>
@endsection
