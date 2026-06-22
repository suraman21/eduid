@extends('layouts.admin')

@section('title', 'Payments')
@section('heading', 'Payments')
@section('subheading', 'School subscription payments')

@section('content')
{{-- Stats Row --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-dark-900 border border-slate-800 rounded-xl p-5">
        <p class="text-xs font-semibold text-slate-500 uppercase mb-1">Total Confirmed (ETB)</p>
        <span class="text-3xl font-bold text-white">{{ number_format($totalConfirmed, 2) }}</span>
    </div>
    <div class="bg-dark-900 border border-slate-800 rounded-xl p-5">
        <p class="text-xs font-semibold text-slate-500 uppercase mb-1">Pending</p>
        <div class="flex items-center gap-3">
            <span class="text-3xl font-bold text-white">{{ $pendingCount }}</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-orange-500/10 text-orange-400">Pending</span>
        </div>
    </div>
</div>

{{-- Filter Bar --}}
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <select name="school_id" class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
            <option value="">All Schools</option>
            @foreach($schools as $school)
                <option value="{{ $school->id }}" {{ request('school_id') == $school->id ? 'selected' : '' }}>{{ $school->name }}</option>
            @endforeach
        </select>
        <select name="status" class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
            <option value="">All Statuses</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
        </select>
        <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm transition">Filter</button>
    </form>
    <a href="{{ route('admin.payments.create') }}" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Record Payment
    </a>
</div>

{{-- Table --}}
<div class="bg-dark-900 rounded-xl border border-slate-800 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-800 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">School</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Plan</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Amount (ETB)</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Method</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Reference</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Date</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                @forelse($payments as $payment)
                    <tr class="hover:bg-slate-800/30 transition">
                        <td class="px-5 py-3 font-medium text-white">{{ $payment->school->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-slate-400">{{ $payment->plan->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-white font-mono">{{ number_format($payment->amount_etb, 2) }}</td>
                        <td class="px-5 py-3 text-slate-400">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                        <td class="px-5 py-3 text-slate-400 font-mono text-xs">{{ $payment->reference_number ?? '—' }}</td>
                        <td class="px-5 py-3 text-slate-400 text-xs">{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : '—' }}</td>
                        <td class="px-5 py-3">
                            @if($payment->status === 'confirmed')
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-500/10 text-green-400">Confirmed</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-500/10 text-yellow-400">Pending</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.payments.show', $payment) }}" class="text-primary-400 hover:text-primary-300 text-xs font-medium transition">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center text-slate-500">
                            <svg class="w-12 h-12 mx-auto mb-3 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            <p>No payments found</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $payments->withQueryString()->links() }}
</div>
@endsection
