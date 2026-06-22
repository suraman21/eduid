@extends('layouts.school')

@section('title', 'Students')
@section('heading', 'Students')
@section('subheading', 'Manage all enrolled students — click a row to view profile, guardians & QR token')

@section('content')
{{-- Actions Bar --}}
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or UID..."
               class="border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-700 focus:outline-none focus:border-indigo-400 w-56 bg-white">
        <select name="class_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-white focus:outline-none focus:border-indigo-400">
            <option value="">All Classes</option>
            @foreach($classes as $class)
                <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>{{ $class->display_name }}</option>
            @endforeach
        </select>
        <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-white focus:outline-none focus:border-indigo-400">
            <option value="">All Status</option>
            @foreach(['active'=>'Active','promoted'=>'Promoted','repeating'=>'Repeating','graduated'=>'Graduated','transferred'=>'Transferred','withdrawn'=>'Withdrawn'] as $val => $label)
                <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm transition">Filter</button>
        @if(request()->hasAny(['search','class_id','status']))
            <a href="{{ route('school.students.index') }}" class="text-sm text-gray-400 hover:text-gray-600">Clear</a>
        @endif
    </form>
    @if(auth()->user()->isSchoolAdmin())
    <a href="{{ route('school.students.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Student
    </a>
    @endif
</div>

{{-- Count --}}
<p class="text-xs text-gray-400 mb-3">Showing {{ $students->firstItem() ?? 0 }}–{{ $students->lastItem() ?? 0 }} of {{ $students->total() }} students</p>

{{-- Table --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left bg-gray-50">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Student</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">UID</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Class</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Gender</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Enrolled</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($students as $student)
                    <tr class="hover:bg-gray-50 transition cursor-pointer" onclick="window.location='{{ route('school.students.show', $student) }}'">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                @if($student->photo_url)
                                    <img src="{{ $student->photo_url }}" class="w-8 h-8 rounded-full object-cover" alt="">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-500">
                                        {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                                    </div>
                                @endif
                                <div>
                                    <p class="font-medium text-gray-900 hover:text-indigo-600 transition">{{ $student->full_name }}</p>
                                    @if($student->first_name_am)
                                        <p class="text-xs text-gray-400">{{ $student->full_name_am }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $student->uid }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $student->schoolClass?->display_name ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-500 capitalize">{{ $student->gender }}</td>
                        <td class="px-5 py-3">
                            @php $statusColors = ['active'=>'bg-green-50 text-green-600','promoted'=>'bg-emerald-50 text-emerald-600','repeating'=>'bg-orange-50 text-orange-600','graduated'=>'bg-blue-50 text-blue-600','withdrawn'=>'bg-red-50 text-red-600','transferred'=>'bg-amber-50 text-amber-600']; @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $statusColors[$student->status ?? 'active'] ?? 'bg-gray-100 text-gray-500' }}">
                                {{ ucfirst($student->status ?? 'active') }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-400 text-xs">{{ $student->enrollment_date?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('school.students.show', $student) }}" class="text-gray-400 hover:text-indigo-500 transition" title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @if(auth()->user()->isSchoolAdmin())
                                <a href="{{ route('school.students.edit', $student) }}" class="text-gray-400 hover:text-indigo-500 transition" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <p>No students found</p>
                            <a href="{{ route('school.students.create') }}" class="text-indigo-500 hover:text-indigo-600 text-sm mt-1 inline-block">Add first student &rarr;</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($students->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">
            {{ $students->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
