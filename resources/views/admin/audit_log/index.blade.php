@extends('layouts.admin')

@section('title', 'Audit Log')
@section('heading', 'Audit Log')
@section('subheading', 'System activity trail')

@section('content')
{{-- Filter Bar --}}
<form method="GET" class="flex flex-wrap items-center gap-2 mb-6">
    <select name="school_id" class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
        <option value="">All Schools</option>
        @foreach($schools as $id => $name)
            <option value="{{ $id }}" {{ request('school_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
        @endforeach
    </select>
    <select name="action" class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
        <option value="">All Actions</option>
        @foreach($actions as $action)
            <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
        @endforeach
    </select>
    <input type="date" name="date_from" value="{{ request('date_from') }}"
           class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
    <input type="date" name="date_to" value="{{ request('date_to') }}"
           class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
    <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm transition">Filter</button>
</form>

{{-- Table --}}
<div class="bg-dark-900 rounded-xl border border-slate-800 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-800 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Timestamp</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Action</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">School</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">User</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                @forelse($logs as $log)
                    <tr class="hover:bg-slate-800/30 transition">
                        <td class="px-5 py-3 text-slate-400 text-xs whitespace-nowrap">
                            {{ $log->created_at->format('d M Y H:i:s') }}
                        </td>
                        <td class="px-5 py-3">
                            <span class="font-mono text-xs px-2 py-0.5 rounded bg-slate-800 text-primary-300">{{ $log->action }}</span>
                        </td>
                        <td class="px-5 py-3 text-slate-400 text-xs">{{ $log->school->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-slate-400 text-xs">{{ $log->user->name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <pre class="text-xs text-slate-500 max-w-xs truncate">{{ json_encode($log->context ?? [], JSON_PRETTY_PRINT) }}</pre>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                            <svg class="w-12 h-12 mx-auto mb-3 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p>No log entries found</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $logs->withQueryString()->links() }}
</div>
@endsection
