@extends('layouts.admin')

@section('title', 'Process Card Order')
@section('heading', 'Process Card Order')
@section('subheading', 'Enter the confirmation code a school shared')

@section('content')

<a href="{{ route('admin.card-orders.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-primary-300 transition mb-5">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    Back to Card Orders
</a>

<div class="max-w-2xl">
    <form method="GET" action="{{ route('admin.card-orders.process') }}" class="bg-dark-800 border border-slate-700/50 rounded-xl p-5 mb-6">
        <label class="block text-sm font-medium text-slate-300 mb-2">Confirmation Code</label>
        <div class="flex gap-2">
            <input type="text" name="code" value="{{ $code }}" placeholder="ORD-AIA-2026-0001" autofocus
                   class="flex-1 bg-dark-900 border border-slate-700 rounded-lg px-4 py-2 text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-primary-500">
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">Look Up</button>
        </div>
    </form>

    @if($code !== '' && !$order)
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg text-sm">
            No order found for code <span class="font-mono">{{ $code }}</span>.
        </div>
    @endif

    @if($order)
        @php
            $statusColors = [
                'pending'=>'bg-amber-500/10 text-amber-400','confirmed'=>'bg-blue-500/10 text-blue-400',
                'printing'=>'bg-indigo-500/10 text-indigo-400','shipped'=>'bg-purple-500/10 text-purple-400',
                'delivered'=>'bg-green-500/10 text-green-400','cancelled'=>'bg-red-500/10 text-red-400',
            ];
        @endphp
        <div class="bg-dark-800 border border-slate-700/50 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="font-mono text-xl text-white">{{ $order->order_code }}</p>
                    <p class="text-sm text-slate-400">{{ $order->school?->name }}</p>
                </div>
                <span class="text-xs px-3 py-1 rounded-full {{ $statusColors[$order->status] ?? 'bg-slate-700 text-slate-300' }} capitalize">{{ $order->status }}</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm mb-5">
                <div><p class="text-xs text-slate-500 uppercase">Cards</p><p class="text-white font-medium">{{ $order->student_count }}</p></div>
                <div><p class="text-xs text-slate-500 uppercase">Type</p><p class="text-white font-medium capitalize">{{ str_replace('_',' ',$order->order_type) }}</p></div>
                <div><p class="text-xs text-slate-500 uppercase">Amount Due</p><p class="text-white font-medium">{{ number_format($order->amount_etb) }} ETB</p></div>
                <div><p class="text-xs text-slate-500 uppercase">Ordered By</p><p class="text-white font-medium">{{ $order->orderedBy?->name ?? '—' }}</p></div>
            </div>

            @php $noPhoto = $students->filter(fn($s) => !$s->hasPhoto())->count(); @endphp
            @if($noPhoto > 0)
                <div class="bg-amber-500/10 border border-amber-500/30 text-amber-400 px-4 py-2 rounded-lg text-sm mb-4">
                    {{ $noPhoto }} student{{ $noPhoto === 1 ? '' : 's' }} {{ $noPhoto === 1 ? 'has' : 'have' }} no photo — {{ $noPhoto === 1 ? 'it' : 'they' }} will print with a placeholder silhouette.
                </div>
            @endif

            <div class="flex items-center gap-3">
                @if($order->isPending())
                    <form method="POST" action="{{ route('admin.card-orders.confirm', $order) }}">
                        @csrf
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">Confirm Order</button>
                    </form>
                @endif
                <a href="{{ route('admin.card-orders.show', $order) }}" class="text-primary-400 hover:text-primary-300 text-sm">Open full order &rarr;</a>
            </div>
        </div>
    @endif
</div>
@endsection
