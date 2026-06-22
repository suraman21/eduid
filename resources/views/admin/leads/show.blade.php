@extends('layouts.admin')

@section('title', 'Lead — '.$lead->name)
@section('heading', $lead->name)
@section('subheading', 'Lead detail')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.leads.index') }}" class="text-slate-500 hover:text-primary-400 text-sm transition flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Leads
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Detail Card --}}
    <div class="lg:col-span-2">
        <div class="bg-dark-900 rounded-xl border border-slate-800 p-6">
            <h3 class="text-sm font-semibold text-white mb-5">Lead Information</h3>
            <dl class="divide-y divide-slate-800/50">
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Name</dt>
                    <dd class="col-span-2 text-sm text-white">{{ $lead->name }}</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">School</dt>
                    <dd class="col-span-2 text-sm text-white">{{ $lead->school_name }}</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Phone</dt>
                    <dd class="col-span-2 text-sm text-white">{{ $lead->phone }}</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Email</dt>
                    <dd class="col-span-2 text-sm text-white">{{ $lead->email ?? '—' }}</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Message</dt>
                    <dd class="col-span-2 text-sm text-slate-300 whitespace-pre-wrap">{{ $lead->message ?? '—' }}</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Submitted</dt>
                    <dd class="col-span-2 text-sm text-slate-400">{{ $lead->created_at->format('d M Y H:i') }}</dd>
                </div>
                @if($lead->contacted_at)
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Contacted</dt>
                    <dd class="col-span-2 text-sm text-slate-400">{{ $lead->contacted_at->format('d M Y H:i') }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    {{-- Status Update --}}
    <div>
        <div class="bg-dark-900 rounded-xl border border-slate-800 p-6">
            <h3 class="text-sm font-semibold text-white mb-5">Update Status</h3>
            <form method="POST" action="{{ route('admin.leads.update', $lead) }}">
                @csrf
                @method('PATCH')

                <div class="mb-4">
                    <label class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                    <select name="status" class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
                        <option value="new" {{ $lead->status === 'new' ? 'selected' : '' }}>New</option>
                        <option value="contacted" {{ $lead->status === 'contacted' ? 'selected' : '' }}>Contacted</option>
                        <option value="demo_scheduled" {{ $lead->status === 'demo_scheduled' ? 'selected' : '' }}>Demo Scheduled</option>
                        <option value="converted" {{ $lead->status === 'converted' ? 'selected' : '' }}>Converted</option>
                        <option value="lost" {{ $lead->status === 'lost' ? 'selected' : '' }}>Lost</option>
                    </select>
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-medium text-slate-400 mb-1">Admin Notes</label>
                    <textarea name="admin_notes" rows="5"
                              class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500 resize-none">{{ old('admin_notes', $lead->admin_notes) }}</textarea>
                </div>

                <button type="submit"
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Save Changes
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
