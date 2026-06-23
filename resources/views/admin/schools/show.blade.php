@extends('layouts.admin')

@section('title', $school->name)
@section('heading', $school->name)
@section('subheading', 'School Code: ' . $school->code)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Main Info --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-dark-900 rounded-xl border border-slate-800 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-white">School Details</h3>
                <a href="{{ route('admin.schools.edit', $school) }}" class="text-xs text-primary-400 hover:text-primary-300">Edit &rarr;</a>
            </div>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs text-slate-500">Name</dt>
                    <dd class="text-white mt-0.5">{{ $school->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Code</dt>
                    <dd class="text-white mt-0.5 font-mono">{{ $school->code }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Email</dt>
                    <dd class="text-white mt-0.5">{{ $school->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Phone</dt>
                    <dd class="text-white mt-0.5">{{ $school->phone ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-xs text-slate-500">Address</dt>
                    <dd class="text-white mt-0.5">{{ $school->address ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Calendar</dt>
                    <dd class="text-white mt-0.5 capitalize">{{ $school->calendar_system ?? 'ethiopian' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Created</dt>
                    <dd class="text-white mt-0.5">{{ $school->created_at->format('M d, Y') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Admins --}}
        <div class="bg-dark-900 rounded-xl border border-slate-800 p-6">
            <h3 class="text-sm font-semibold text-white mb-4">School Admins</h3>
            @forelse($school->users as $admin)
                <div class="flex items-center gap-3 py-2">
                    <div class="w-8 h-8 rounded-full bg-primary-500/20 flex items-center justify-center text-xs font-bold text-primary-400">
                        {{ strtoupper(substr($admin->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm text-white">{{ $admin->name }}</p>
                        <p class="text-xs text-slate-500">{{ $admin->email }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No school admins assigned</p>
            @endforelse
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        {{-- Status Card --}}
        <div class="bg-dark-900 rounded-xl border border-slate-800 p-6">
            <h3 class="text-sm font-semibold text-white mb-4">Status</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Account</span>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $school->is_active ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' }}">
                        {{ $school->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Subscription</span>
                    <span class="text-xs capitalize {{ $school->subscription_status === 'active' ? 'text-green-400' : ($school->subscription_status === 'grace' ? 'text-amber-400' : 'text-red-400') }}">
                        {{ $school->subscription_status ?? 'active' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Plan</span>
                    <span class="text-xs capitalize text-white">{{ $school->plan_tier ?? 'starter' }}</span>
                </div>
                @if($school->subscription_expires_at)
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Expires</span>
                    <span class="text-xs text-slate-300">{{ $school->subscription_expires_at->format('M d, Y') }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Counts --}}
        <div class="bg-dark-900 rounded-xl border border-slate-800 p-6">
            <h3 class="text-sm font-semibold text-white mb-4">Usage</h3>
            <div class="space-y-3">
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-500">Students</span>
                        <span class="text-white">{{ $school->students_count ?? 0 }} / {{ $school->max_students }}</span>
                    </div>
                    @php $pct = $school->max_students > 0 ? min(100, (($school->students_count ?? 0) / $school->max_students) * 100) : 0; @endphp
                    <div class="w-full bg-slate-800 rounded-full h-1.5">
                        <div class="bg-primary-500 h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-500">Staff</span>
                        <span class="text-white">{{ $school->users_count ?? 0 }} / {{ $school->max_teachers }}</span>
                    </div>
                    @php $pct2 = $school->max_teachers > 0 ? min(100, (($school->users_count ?? 0) / $school->max_teachers) * 100) : 0; @endphp
                    <div class="w-full bg-slate-800 rounded-full h-1.5">
                        <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $pct2 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-6 flex items-center gap-4">
    <a href="{{ route('admin.schools.index') }}" class="text-sm text-slate-400 hover:text-white transition">&larr; Back to Schools</a>
    <a href="{{ route('admin.card-template.edit', $school) }}"
       class="text-sm bg-dark-800 border border-slate-700 hover:border-primary-500 text-slate-300 px-4 py-2 rounded-lg transition">
        ID Card Template
    </a>
</div>
@endsection
