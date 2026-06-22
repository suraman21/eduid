@extends('layouts.school')

@section('title', $student->full_name . ' — ID Card')
@section('heading', 'Student ID Card')
@section('subheading', $student->full_name . ' · ' . ($student->schoolClass?->display_name ?? '—'))

@section('styles')
<style>
/* ── Print: one card per page, credit-card dimensions ── */
@media print {
    body, html { margin: 0; padding: 0; background: white; }
    .print\:hidden { display: none !important; }
    @page { size: 85.6mm 54mm; margin: 0; }
    .id-card-wrapper { padding: 0; }
    .id-card { width: 85.6mm; height: 54mm; border-radius: 0; box-shadow: none; page-break-after: always; }
}
</style>
@endsection

@section('content')

{{-- Controls --}}
<div class="flex items-center justify-between mb-6 print:hidden">
    <a href="{{ url()->previous() }}" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-gray-600 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back
    </a>
    <button data-print class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Print Card
    </button>
</div>

<div class="id-card-wrapper flex justify-center py-4">
    @include('school.id_cards._card', ['student' => $student, 'school' => $school, 'qrSvg' => $qrSvg])
</div>

@endsection
