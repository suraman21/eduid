@extends('layouts.school')

@section('title', $member->name)
@section('heading', $member->name)
@section('subheading', $member->job_title ?? ucfirst(str_replace('_', ' ', $member->role)))

@section('content')
<div class="max-w-3xl space-y-6">
    {{-- Profile Card --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-indigo-100 flex items-center justify-center text-xl font-bold text-indigo-500">
                    {{ strtoupper(substr($member->name, 0, 2)) }}
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $member->name }}</h2>
                    <p class="text-sm text-gray-500">{{ $member->email }}</p>
                    <div class="flex items-center gap-2 mt-1">
                        @php $roleColors = ['school_admin'=>'bg-purple-50 text-purple-600','teacher'=>'bg-blue-50 text-blue-600','staff'=>'bg-gray-100 text-gray-600']; @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $roleColors[$member->role] ?? 'bg-gray-100 text-gray-500' }}">
                            {{ ucfirst(str_replace('_', ' ', $member->role)) }}
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $member->is_active ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }}">
                            {{ $member->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
            </div>
            @if(auth()->user()->isSchoolAdmin())
                <a href="{{ route('school.staff.edit', $member) }}" class="text-sm text-indigo-500 hover:text-indigo-600 font-medium">Edit</a>
            @endif
        </div>
    </div>

    {{-- Details --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Details</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-400">Phone</dt>
                <dd class="text-gray-900 mt-0.5">{{ $member->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-400">Employee ID</dt>
                <dd class="text-gray-900 mt-0.5 font-mono">{{ $member->employee_id ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-400">Job Title</dt>
                <dd class="text-gray-900 mt-0.5">{{ $member->job_title ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-400">Last Login</dt>
                <dd class="text-gray-900 mt-0.5">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Permissions --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Permissions</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            @foreach([
                'can_login' => 'Can log in',
                'is_homeroom' => 'Homeroom teacher',
                'is_coordinator' => 'Department coordinator',
                'can_record_staff_attendance' => 'Can record staff attendance',
            ] as $field => $label)
                <div class="flex items-center gap-2">
                    @if($member->$field)
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @else
                        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    @endif
                    <span class="text-gray-600">{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <a href="{{ route('school.staff.index') }}" class="inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Back to Staff</a>
</div>
@endsection
