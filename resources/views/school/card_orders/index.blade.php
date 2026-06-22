@extends('layouts.school')

@section('title', 'ID Cards')
@section('heading', 'ID Card Orders')
@section('subheading', 'Order PVC cards — printed and delivered by Arkeon')

@section('content')

@if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
@endif

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-400">{{ $orders->total() }} order{{ $orders->total() === 1 ? '' : 's' }}</p>
    @if(auth()->user()->isSchoolAdmin())
        <a href="{{ route('school.card-orders.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Order Cards
        </a>
    @endif
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Order Code</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Type</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-center">Cards</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-right">Amount</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-center">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Date</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($orders as $order)
                    @php
                        $statusColors = [
                            'pending'=>'bg-amber-50 text-amber-600','confirmed'=>'bg-blue-50 text-blue-600',
                            'printing'=>'bg-indigo-50 text-indigo-600','shipped'=>'bg-purple-50 text-purple-600',
                            'delivered'=>'bg-green-50 text-green-600','cancelled'=>'bg-red-50 text-red-500',
                        ];
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3 font-mono text-gray-900">{{ $order->order_code }}</td>
                        <td class="px-5 py-3 text-gray-600 capitalize">{{ str_replace('_', ' ', $order->order_type) }}</td>
                        <td class="px-5 py-3 text-center text-gray-600">{{ $order->student_count }}</td>
                        <td class="px-5 py-3 text-right text-gray-600">{{ number_format($order->amount_etb) }} ETB</td>
                        <td class="px-5 py-3 text-center">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-500' }} capitalize">{{ $order->status }}</span>
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ $order->created_at->format('M d, Y') }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('school.card-orders.show', $order) }}" class="text-indigo-500 hover:text-indigo-700 text-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1"/></svg>
                            <p>No card orders yet</p>
                            @if(auth()->user()->isSchoolAdmin())
                                <a href="{{ route('school.card-orders.create') }}" class="text-indigo-500 hover:text-indigo-600 text-sm mt-1 inline-block">Order cards &rarr;</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
