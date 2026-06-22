@extends('layouts.school')

@section('title', 'Attendance')
@section('heading', 'Attendance')
@section('subheading', 'Daily attendance sessions')

@section('content')
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <select name="class_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-white focus:outline-none focus:border-indigo-400">
            <option value="">All Classes</option>
            @foreach($classes as $class)
                <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>{{ $class->display_name }}</option>
            @endforeach
        </select>
        <input type="date" name="date" value="{{ request('date') }}"
               class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-white focus:outline-none focus:border-indigo-400">
        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm transition">Filter</button>
        @if(request()->hasAny(['class_id','date']))
            <a href="{{ route('school.attendance.index') }}" class="text-sm text-gray-400 hover:text-gray-600">Clear</a>
        @endif
    </form>
    <a href="{{ route('school.attendance.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Take Attendance
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left bg-gray-50">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Date</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Class</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Session</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Recorded By</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Marked</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($sessions as $session)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3 text-gray-900 font-medium">{{ $session->session_date->format('M d, Y') }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $session->schoolClass?->display_name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 capitalize">{{ str_replace('_', ' ', $session->session_type) }}</span>
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ $session->teacher?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $session->records_count }} student{{ $session->records_count === 1 ? '' : 's' }}</td>
                        <td class="px-5 py-3">
                            <a href="{{ route('school.attendance.show', $session) }}" class="text-gray-400 hover:text-indigo-500 transition" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <p>No attendance recorded yet</p>
                            <a href="{{ route('school.attendance.create') }}" class="text-indigo-500 hover:text-indigo-600 text-sm mt-1 inline-block">Take attendance &rarr;</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($sessions->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">
            {{ $sessions->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
