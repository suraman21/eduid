@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', 'EduID Platform Overview')

@section('content')
{{-- Stats Grid --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    {{-- Schools --}}
    <div class="stat-card bg-dark-900 rounded-xl border border-slate-800 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-primary-500/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full bg-green-500/10 text-green-400">{{ $stats['active_schools'] }} active</span>
        </div>
        <p class="text-2xl font-bold text-white">{{ $stats['total_schools'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Schools</p>
    </div>

    {{-- Students --}}
    <div class="stat-card bg-dark-900 rounded-xl border border-slate-800 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-white">{{ number_format($stats['total_students']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Students</p>
    </div>

    {{-- Staff --}}
    <div class="stat-card bg-dark-900 rounded-xl border border-slate-800 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-white">{{ $stats['total_staff'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Staff</p>
    </div>

    {{-- Revenue --}}
    <div class="stat-card bg-dark-900 rounded-xl border border-slate-800 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-white">{{ number_format($stats['total_revenue']) }} <span class="text-sm font-normal text-slate-500">ETB</span></p>
        <p class="text-xs text-slate-500 mt-1">Total Revenue</p>
    </div>
</div>

{{-- Second Row --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <div class="stat-card bg-dark-900 rounded-xl border border-slate-800 p-5">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-8 h-8 rounded-lg bg-orange-500/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <p class="text-xl font-bold text-white">{{ $stats['pending_leads'] }}</p>
        </div>
        <p class="text-xs text-slate-500">New Leads</p>
    </div>
    <div class="stat-card bg-dark-900 rounded-xl border border-slate-800 p-5">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1"/></svg>
            </div>
            <p class="text-xl font-bold text-white">{{ $stats['pending_orders'] }}</p>
        </div>
        <p class="text-xs text-slate-500">Pending Card Orders</p>
    </div>
    <div class="stat-card bg-dark-900 rounded-xl border border-slate-800 p-5">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <p class="text-xl font-bold text-white">{{ $stats['total_teachers'] }}</p>
        </div>
        <p class="text-xs text-slate-500">Teachers</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Recent Schools --}}
    <div class="bg-dark-900 rounded-xl border border-slate-800">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">Recent Schools</h3>
            <a href="{{ route('admin.schools.index') }}" class="text-xs text-primary-400 hover:text-primary-300">View all &rarr;</a>
        </div>
        <div class="divide-y divide-slate-800/50">
            @forelse($recentSchools as $school)
                <div class="px-5 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-xs font-bold text-primary-400">
                            {{ strtoupper(substr($school->name, 0, 2)) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-200">{{ $school->name }}</p>
                            <p class="text-xs text-slate-500">{{ $school->code }} &middot; {{ ucfirst($school->plan_tier ?? 'starter') }}</p>
                        </div>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $school->is_active ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' }}">
                        {{ $school->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500">No schools yet</div>
            @endforelse
        </div>
    </div>

    {{-- Recent Leads --}}
    <div class="bg-dark-900 rounded-xl border border-slate-800">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">Recent Leads</h3>
        </div>
        <div class="divide-y divide-slate-800/50">
            @forelse($recentLeads as $lead)
                <div class="px-5 py-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-200">{{ $lead->name }}</p>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $lead->status === 'new' ? 'bg-amber-500/10 text-amber-400' : 'bg-slate-700 text-slate-400' }}">
                            {{ ucfirst($lead->status) }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $lead->school_name }} &middot; {{ $lead->phone }}</p>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500">No leads yet</div>
            @endforelse
        </div>
    </div>
</div>

{{-- Plan Distribution --}}
@if(count($schoolsByPlan) > 0)
<div class="mt-6 bg-dark-900 rounded-xl border border-slate-800 p-5">
    <h3 class="text-sm font-semibold text-white mb-4">Schools by Plan</h3>
    <div class="flex gap-4">
        @foreach($schoolsByPlan as $plan => $count)
            <div class="flex-1 text-center p-3 rounded-lg bg-slate-800/50">
                <p class="text-lg font-bold text-white">{{ $count }}</p>
                <p class="text-xs text-slate-400 capitalize">{{ $plan ?: 'No plan' }}</p>
            </div>
        @endforeach
    </div>
</div>
@endif
@endsection
