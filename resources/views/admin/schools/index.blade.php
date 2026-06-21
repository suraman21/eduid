@extends('layouts.admin')

@section('title', 'Schools')
@section('heading', 'Schools')
@section('subheading', 'Manage all registered schools')

@section('content')
{{-- Actions Bar --}}
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search schools..."
               class="bg-dark-900 border border-slate-700 rounded-lg px-4 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary-500 w-64">
        <select name="plan" class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
            <option value="">All Plans</option>
            <option value="starter" {{ request('plan') === 'starter' ? 'selected' : '' }}>Starter</option>
            <option value="standard" {{ request('plan') === 'standard' ? 'selected' : '' }}>Standard</option>
            <option value="premium" {{ request('plan') === 'premium' ? 'selected' : '' }}>Premium</option>
        </select>
        <select name="status" class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
            <option value="">All Status</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm transition">Filter</button>
    </form>
    <a href="{{ route('admin.schools.create') }}" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add School
    </a>
</div>

{{-- Table --}}
<div class="bg-dark-900 rounded-xl border border-slate-800 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-800 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">School</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Code</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Plan</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Students</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Staff</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Subscription</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                @forelse($schools as $school)
                    <tr class="hover:bg-slate-800/30 transition">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-xs font-bold text-primary-400">
                                    {{ strtoupper(substr($school->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-white">{{ $school->name }}</p>
                                    @if($school->email)
                                        <p class="text-xs text-slate-500">{{ $school->email }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-slate-400 font-mono text-xs">{{ $school->code }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                {{ $school->plan_tier === 'premium' ? 'bg-amber-500/10 text-amber-400' :
                                   ($school->plan_tier === 'standard' ? 'bg-blue-500/10 text-blue-400' : 'bg-slate-700 text-slate-300') }}">
                                {{ ucfirst($school->plan_tier ?? 'starter') }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-slate-400">{{ $school->students_count ?? 0 }}</td>
                        <td class="px-5 py-3 text-slate-400">{{ $school->users_count ?? 0 }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $school->is_active ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' }}">
                                {{ $school->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            @php
                                $subColors = ['active' => 'text-green-400', 'grace' => 'text-amber-400', 'expired' => 'text-red-400', 'suspended' => 'text-red-400'];
                            @endphp
                            <span class="text-xs {{ $subColors[$school->subscription_status] ?? 'text-slate-400' }}">
                                {{ ucfirst($school->subscription_status ?? 'active') }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.schools.show', $school) }}" class="text-slate-500 hover:text-primary-400 transition" title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ route('admin.schools.edit', $school) }}" class="text-slate-500 hover:text-primary-400 transition" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center text-slate-500">
                            <svg class="w-12 h-12 mx-auto mb-3 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <p>No schools found</p>
                            <a href="{{ route('admin.schools.create') }}" class="text-primary-400 hover:text-primary-300 text-sm mt-2 inline-block">Add your first school &rarr;</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($schools->hasPages())
        <div class="px-5 py-3 border-t border-slate-800">
            {{ $schools->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
