@extends('layouts.school')

@section('title', 'Staff')
@section('heading', 'Staff')
@section('subheading', 'Manage teachers, admins and support staff')

@section('content')
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email or ID..."
               class="border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-700 focus:outline-none focus:border-indigo-400 w-56 bg-white">
        <select name="role" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-white focus:outline-none focus:border-indigo-400">
            <option value="">All Roles</option>
            @foreach(['school_admin'=>'Admin','teacher'=>'Teacher','staff'=>'Staff'] as $val => $label)
                <option value="{{ $val }}" {{ request('role') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm transition">Filter</button>
        @if(request()->hasAny(['search','role']))
            <a href="{{ route('school.staff.index') }}" class="text-sm text-gray-400 hover:text-gray-600">Clear</a>
        @endif
    </form>
    @if(auth()->user()->isSchoolAdmin())
    <a href="{{ route('school.staff.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Staff
    </a>
    @endif
</div>

<p class="text-xs text-gray-400 mb-3">Showing {{ $staff->firstItem() ?? 0 }}–{{ $staff->lastItem() ?? 0 }} of {{ $staff->total() }} staff members</p>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left bg-gray-50">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Name</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Email</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Role</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Employee ID</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($staff as $member)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-500">
                                    {{ strtoupper(substr($member->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $member->name }}</p>
                                    @if($member->job_title)
                                        <p class="text-xs text-gray-400">{{ $member->job_title }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $member->email }}</td>
                        <td class="px-5 py-3">
                            @php $roleColors = ['school_admin'=>'bg-purple-50 text-purple-600','teacher'=>'bg-blue-50 text-blue-600','staff'=>'bg-gray-100 text-gray-600']; @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $roleColors[$member->role] ?? 'bg-gray-100 text-gray-500' }}">
                                {{ ucfirst(str_replace('_', ' ', $member->role)) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $member->employee_id ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $member->is_active ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }}">
                                {{ $member->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('school.staff.show', $member) }}" class="text-gray-400 hover:text-indigo-500 transition" title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @if(auth()->user()->isSchoolAdmin())
                                <a href="{{ route('school.staff.edit', $member) }}" class="text-gray-400 hover:text-indigo-500 transition" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <p>No staff members found</p>
                            @if(auth()->user()->isSchoolAdmin())
                                <a href="{{ route('school.staff.create') }}" class="text-indigo-500 hover:text-indigo-600 text-sm mt-1 inline-block">Add first staff member &rarr;</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($staff->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">
            {{ $staff->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
