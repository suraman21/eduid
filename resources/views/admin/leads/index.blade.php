@extends('layouts.admin')

@section('title', 'Leads')
@section('heading', 'Leads')
@section('subheading', 'Marketing inquiries from the contact form')

@section('content')
{{-- Stats Row --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-dark-900 border border-slate-800 rounded-xl p-5">
        <p class="text-xs font-semibold text-slate-500 uppercase mb-1">New Leads</p>
        <div class="flex items-center gap-3">
            <span class="text-3xl font-bold text-white">{{ $newCount }}</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-500/10 text-yellow-400">New</span>
        </div>
    </div>
    <div class="bg-dark-900 border border-slate-800 rounded-xl p-5">
        <p class="text-xs font-semibold text-slate-500 uppercase mb-1">Total Leads</p>
        <span class="text-3xl font-bold text-white">{{ $leads->total() }}</span>
    </div>
</div>

{{-- Filter Bar --}}
<form method="GET" class="flex flex-wrap items-center gap-2 mb-6">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name / school / phone..."
           class="bg-dark-900 border border-slate-700 rounded-lg px-4 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary-500 w-72">
    <select name="status" class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
        <option value="">All Statuses</option>
        <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
        <option value="contacted" {{ request('status') === 'contacted' ? 'selected' : '' }}>Contacted</option>
        <option value="demo_scheduled" {{ request('status') === 'demo_scheduled' ? 'selected' : '' }}>Demo Scheduled</option>
        <option value="converted" {{ request('status') === 'converted' ? 'selected' : '' }}>Converted</option>
        <option value="lost" {{ request('status') === 'lost' ? 'selected' : '' }}>Lost</option>
    </select>
    <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm transition">Filter</button>
</form>

{{-- Table --}}
<div class="bg-dark-900 rounded-xl border border-slate-800 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-800 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Name</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">School</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Phone</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Submitted</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                @forelse($leads as $lead)
                    @php
                        $statusClasses = [
                            'new'            => 'bg-yellow-500/10 text-yellow-400',
                            'contacted'      => 'bg-blue-500/10 text-blue-400',
                            'demo_scheduled' => 'bg-purple-500/10 text-purple-400',
                            'converted'      => 'bg-green-500/10 text-green-400',
                            'lost'           => 'bg-slate-700 text-slate-400',
                        ];
                        $statusLabels = [
                            'new'            => 'New',
                            'contacted'      => 'Contacted',
                            'demo_scheduled' => 'Demo Scheduled',
                            'converted'      => 'Converted',
                            'lost'           => 'Lost',
                        ];
                    @endphp
                    <tr class="hover:bg-slate-800/30 transition">
                        <td class="px-5 py-3 font-medium text-white">{{ $lead->name }}</td>
                        <td class="px-5 py-3 text-slate-400">{{ $lead->school_name }}</td>
                        <td class="px-5 py-3 text-slate-400">{{ $lead->phone }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $statusClasses[$lead->status] ?? 'bg-slate-700 text-slate-400' }}">
                                {{ $statusLabels[$lead->status] ?? ucfirst($lead->status) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-slate-400 text-xs">{{ $lead->created_at->format('d M Y H:i') }}</td>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.leads.show', $lead) }}" class="text-primary-400 hover:text-primary-300 text-xs font-medium transition">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                            <svg class="w-12 h-12 mx-auto mb-3 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <p>No leads found</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $leads->withQueryString()->links() }}
</div>
@endsection
