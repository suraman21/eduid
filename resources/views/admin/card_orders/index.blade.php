@extends('layouts.admin')

@section('title', 'Card Orders')
@section('heading', 'Card Orders')
@section('subheading', 'All card orders across schools')

@section('content')

@if(session('success'))
    <div class="mb-4 bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
@endif

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex items-center gap-2">
        <input type="text" name="code" value="{{ request('code') }}" placeholder="Search order code..."
               class="bg-dark-900 border border-slate-700 rounded-lg px-4 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary-500 w-56">
        <select name="status" class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
            <option value="">All Status</option>
            @foreach(['pending','confirmed','printing','shipped','delivered','cancelled'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm transition">Filter</button>
    </form>
    <a href="{{ route('admin.card-orders.process') }}" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Process a Code
    </a>
</div>

<div class="bg-dark-800 border border-slate-700/50 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700/50 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Order Code</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">School</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Type</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase text-center">Cards</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase text-right">Amount</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase text-center">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/30">
                @forelse($orders as $order)
                    @php
                        $statusColors = [
                            'pending'=>'bg-amber-500/10 text-amber-400','confirmed'=>'bg-blue-500/10 text-blue-400',
                            'printing'=>'bg-indigo-500/10 text-indigo-400','shipped'=>'bg-purple-500/10 text-purple-400',
                            'delivered'=>'bg-green-500/10 text-green-400','cancelled'=>'bg-red-500/10 text-red-400',
                        ];
                    @endphp
                    <tr class="hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3 font-mono text-white">{{ $order->order_code }}</td>
                        <td class="px-5 py-3 text-slate-300">{{ $order->school?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-slate-400 capitalize">{{ str_replace('_',' ',$order->order_type) }}</td>
                        <td class="px-5 py-3 text-center text-slate-300">{{ $order->student_count }}</td>
                        <td class="px-5 py-3 text-right text-slate-300">{{ number_format($order->amount_etb) }} ETB</td>
                        <td class="px-5 py-3 text-center"><span class="text-xs px-2 py-0.5 rounded-full {{ $statusColors[$order->status] ?? 'bg-slate-700 text-slate-300' }} capitalize">{{ $order->status }}</span></td>
                        <td class="px-5 py-3 text-right"><a href="{{ route('admin.card-orders.show', $order) }}" class="text-primary-400 hover:text-primary-300 text-sm">Manage</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No card orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
        <div class="px-5 py-3 border-t border-slate-700/50">{{ $orders->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
