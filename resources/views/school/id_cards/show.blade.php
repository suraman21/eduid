@extends('layouts.school')

@section('title', $student->full_name . ' — ID Card Preview')
@section('heading', 'ID Card Preview')
@section('subheading', $student->full_name . ' · ' . ($student->schoolClass?->display_name ?? '—'))

@section('content')

{{-- Controls --}}
<div class="flex items-center justify-between mb-6">
    <a href="{{ url()->previous() }}" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-gray-600 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back
    </a>
    <a href="{{ route('school.card-orders.create') }}"
       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        Order Cards
    </a>
</div>

<div class="flex justify-center py-4">
    @include('school.id_cards._card', ['student' => $student, 'school' => $school, 'qrSvg' => $qrSvg, 'preview' => true])
</div>

{{-- Golden-rule explainer: schools see a preview only; Arkeon prints the real card. --}}
<div class="max-w-md mx-auto mt-6 bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-center">
    <p class="text-sm font-medium text-indigo-900">This is a preview of your student's ID card.</p>
    <p class="text-xs text-indigo-700 mt-1.5 leading-relaxed">
        Professional PVC cards are printed and delivered by Arkeon. Place a card order
        and we'll print and ship the finished cards to your school.
    </p>
    <a href="{{ route('school.card-orders.create') }}" class="inline-block mt-3 text-xs font-semibold text-indigo-600 hover:text-indigo-800">Order ID Cards &rarr;</a>
</div>

@endsection
