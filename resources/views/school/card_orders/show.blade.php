@extends('layouts.school')

@section('title', 'Order ' . $order->order_code)
@section('heading', $order->order_code)
@section('subheading', ucfirst(str_replace('_',' ',$order->order_type)) . ' · ' . $order->student_count . ' cards')

@section('content')

@if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
@endif

<div class="max-w-3xl space-y-6">

    {{-- Confirmation code call-out --}}
    @if($order->isPending())
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
            <p class="text-sm text-amber-800 mb-1">Share this confirmation code with Arkeon (WhatsApp / Telegram / phone) to confirm your order:</p>
            <p class="font-mono text-2xl font-bold text-amber-900 tracking-wider">{{ $order->order_code }}</p>
        </div>
    @endif

    {{-- Status timeline --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        @php
            $steps = ['pending'=>'Pending','confirmed'=>'Confirmed','printing'=>'Printing','shipped'=>'Shipped','delivered'=>'Delivered'];
            $order_idx = array_search($order->status, array_keys($steps), true);
        @endphp
        @if($order->isCancelled())
            <div class="text-center py-2">
                <span class="text-sm px-3 py-1 rounded-full bg-red-50 text-red-500">Cancelled</span>
                @if($order->cancel_reason)<p class="text-xs text-gray-400 mt-2">{{ $order->cancel_reason }}</p>@endif
            </div>
        @else
            <div class="flex items-center justify-between">
                @foreach($steps as $key => $label)
                    @php $done = $order_idx !== false && array_search($key, array_keys($steps), true) <= $order_idx; @endphp
                    <div class="flex flex-col items-center flex-1">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold {{ $done ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-400' }}">{{ $loop->iteration }}</div>
                        <span class="text-xs mt-1 {{ $done ? 'text-gray-700' : 'text-gray-400' }}">{{ $label }}</span>
                    </div>
                    @if(!$loop->last)
                        <div class="h-0.5 flex-1 {{ ($order_idx !== false && $loop->index < $order_idx) ? 'bg-indigo-600' : 'bg-gray-100' }}"></div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    {{-- Order details --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
        <div><p class="text-xs text-gray-400 uppercase">Cards</p><p class="font-medium text-gray-900">{{ $order->student_count }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Amount</p><p class="font-medium text-gray-900">{{ number_format($order->amount_etb) }} ETB</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Ordered By</p><p class="font-medium text-gray-900">{{ $order->orderedBy?->name ?? '—' }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Placed</p><p class="font-medium text-gray-900">{{ $order->created_at->format('M d, Y') }}</p></div>
    </div>

    @if($order->tracking_note)
        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-700">{{ $order->tracking_note }}</div>
    @endif

    {{-- Students --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50"><p class="font-medium text-gray-900">Students ({{ $students->count() }})</p></div>
        <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
            @foreach($students as $student)
                <div class="px-5 py-2.5 flex items-center gap-3">
                    <p class="flex-1 text-gray-900">{{ $student->full_name }}</p>
                    <p class="text-xs text-gray-400 font-mono">{{ $student->uid }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex items-center justify-between">
        <a href="{{ route('school.card-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to Orders</a>
        @if(auth()->user()->isSchoolAdmin() && $order->canBeCancelledBy(auth()->user()))
            <form method="POST" action="{{ route('school.card-orders.cancel', $order) }}" data-confirm="Cancel this order?">
                @csrf
                <button type="submit" class="text-sm text-red-500 hover:text-red-700">Cancel Order</button>
            </form>
        @endif
    </div>
</div>
@endsection
