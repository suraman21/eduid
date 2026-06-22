@extends('layouts.school')

@section('title', 'ID Card Preview — ' . $cardOrder->order_code)
@section('heading', 'ID Card Preview')
@section('subheading', $cardOrder->order_code . ' · ' . $students->count() . ' cards')

@section('styles')
<style>
/* ── Print: grid of credit-card-sized pages ── */
@media print {
    body, html { margin: 0; padding: 0; background: white; }
    .print\:hidden { display: none !important; }
    @page { size: A4; margin: 10mm; }
    .cards-grid { display: grid; grid-template-columns: repeat(2, 85.6mm); gap: 6mm; }
    .id-card { width: 85.6mm !important; height: 54mm !important; border-radius: 0 !important; box-shadow: none !important; page-break-inside: avoid; }
}
</style>
@endsection

@section('content')

{{-- Controls --}}
<div class="flex items-center justify-between mb-6 print:hidden">
    <a href="{{ route('school.card-orders.show', $cardOrder) }}" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-gray-600 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Order
    </a>
    <div class="flex items-center gap-3">
        <span class="text-sm text-gray-400">{{ $students->count() }} cards</span>
        <button data-print class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print All Cards
        </button>
    </div>
</div>

@if($students->isEmpty())
    <div class="text-center py-16 text-gray-400 text-sm">No students found in this order.</div>
@else
    <div class="cards-grid flex flex-wrap gap-6 print:gap-0">
        @foreach($students as $item)
            @include('school.id_cards._card', [
                'student' => $item['student'],
                'school'  => $school,
                'qrSvg'   => $item['qrSvg'],
            ])
        @endforeach
    </div>
@endif

@endsection
