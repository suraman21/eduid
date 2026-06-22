@extends('layouts.school')

@section('title', 'ID Card Preview — ' . $cardOrder->order_code)
@section('heading', 'ID Card Preview')
@section('subheading', $cardOrder->order_code . ' · ' . $students->count() . ' cards')

@section('content')

{{-- Controls --}}
<div class="flex items-center justify-between mb-6">
    <a href="{{ route('school.card-orders.show', $cardOrder) }}" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-gray-600 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Order
    </a>
    <span class="text-sm text-gray-400">{{ $students->count() }} cards in this order</span>
</div>

{{-- Golden-rule explainer --}}
<div class="mb-6 bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-sm text-indigo-800">
    These are previews of the cards in this order. Arkeon prints the professional PVC
    cards and ships them to your school — you don't need to print anything yourself.
</div>

@if($students->isEmpty())
    <div class="text-center py-16 text-gray-400 text-sm">No students found in this order.</div>
@else
    <div class="flex flex-wrap gap-6 justify-center">
        @foreach($students as $item)
            @include('school.id_cards._card', [
                'student' => $item['student'],
                'school'  => $school,
                'qrSvg'   => $item['qrSvg'],
                'preview' => true,
            ])
        @endforeach
    </div>
@endif

@endsection
