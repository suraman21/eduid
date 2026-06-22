@extends('layouts.admin')

@section('title', 'Order ' . $order->order_code)
@section('heading', $order->order_code)
@section('subheading', $order->school?->name . ' · ' . $order->student_count . ' cards')

@section('content')

@if(session('success'))
    <div class="mb-4 bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
@endif

@php
    $statusColors = [
        'pending'=>'bg-amber-500/10 text-amber-400','confirmed'=>'bg-blue-500/10 text-blue-400',
        'printing'=>'bg-indigo-500/10 text-indigo-400','shipped'=>'bg-purple-500/10 text-purple-400',
        'delivered'=>'bg-green-500/10 text-green-400','cancelled'=>'bg-red-500/10 text-red-400',
    ];
    $next = ['confirmed'=>'printing','printing'=>'shipped','shipped'=>'delivered'][$order->status] ?? null;
@endphp

<div class="max-w-3xl space-y-6">
    <div class="bg-dark-800 border border-slate-700/50 rounded-xl p-6">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="font-mono text-xl text-white">{{ $order->order_code }}</p>
                <p class="text-sm text-slate-400">{{ $order->school?->name }} · ordered by {{ $order->orderedBy?->name ?? '—' }}</p>
            </div>
            <span class="text-xs px-3 py-1 rounded-full {{ $statusColors[$order->status] ?? 'bg-slate-700 text-slate-300' }} capitalize">{{ $order->status }}</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm mb-5">
            <div><p class="text-xs text-slate-500 uppercase">Cards</p><p class="text-white font-medium">{{ $order->student_count }}</p></div>
            <div><p class="text-xs text-slate-500 uppercase">Type</p><p class="text-white font-medium capitalize">{{ str_replace('_',' ',$order->order_type) }}</p></div>
            <div><p class="text-xs text-slate-500 uppercase">Amount</p><p class="text-white font-medium">{{ number_format($order->amount_etb) }} ETB</p></div>
            <div><p class="text-xs text-slate-500 uppercase">Placed</p><p class="text-white font-medium">{{ $order->created_at->format('M d, Y') }}</p></div>
        </div>

        {{-- Lifecycle actions --}}
        @unless($order->isCancelled() || $order->isDelivered())
            <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-slate-700/50">
                @if($order->isPending())
                    <form method="POST" action="{{ route('admin.card-orders.confirm', $order) }}">
                        @csrf
                        <button class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">Confirm Order</button>
                    </form>
                @elseif($next)
                    <form method="POST" action="{{ route('admin.card-orders.status', $order) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="status" value="{{ $next }}">
                        <input type="text" name="tracking_note" placeholder="Tracking note (optional)"
                               class="bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary-500 w-64">
                        <button class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition capitalize">Mark {{ $next }}</button>
                    </form>
                @endif

                @if($order->canBeCancelledBy(auth()->user()))
                    <form method="POST" action="{{ route('admin.card-orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?');">
                        @csrf
                        <button class="text-red-400 hover:text-red-300 text-sm">Cancel</button>
                    </form>
                @endif
            </div>
        @endunless

        @if($order->cancel_reason)
            <p class="text-xs text-slate-500 mt-3">Cancellation reason: {{ $order->cancel_reason }}</p>
        @endif
    </div>

    {{-- Students --}}
    <div class="bg-dark-800 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-700/50"><p class="text-white font-medium">Students ({{ $students->count() }})</p></div>
        <div class="divide-y divide-slate-700/30 max-h-96 overflow-y-auto">
            @foreach($students as $student)
                <div class="px-5 py-2.5 flex items-center gap-3 text-sm">
                    <p class="flex-1 text-slate-200">{{ $student->full_name }}</p>
                    <p class="text-xs text-slate-500 font-mono">{{ $student->uid }}</p>
                    @unless($student->hasPhoto())
                        <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400">No photo</span>
                    @endunless
                </div>
            @endforeach
        </div>
    </div>

    <a href="{{ route('admin.card-orders.index') }}" class="inline-block text-sm text-slate-400 hover:text-slate-200">&larr; Back to Orders</a>
</div>
@endsection
