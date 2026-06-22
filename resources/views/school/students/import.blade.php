@extends('layouts.school')

@section('title', 'Bulk Import Students')
@section('heading', 'Bulk Import Students')
@section('subheading', 'Upload a CSV file to import multiple students at once')

@section('content')
@if(!isset($preview))
    {{-- Step 1: Upload Form --}}
    <div class="max-w-xl">
        <div class="bg-dark-900 rounded-xl border border-slate-800 p-6">
            {{-- Download Template --}}
            <div class="mb-6 pb-6 border-b border-slate-800">
                <p class="text-sm text-slate-400 mb-3">Start by downloading the CSV template with the correct column headers.</p>
                <a href="{{ route('school.students.import.template') }}"
                   class="inline-flex items-center gap-2 text-sm text-primary-400 hover:text-primary-300 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download CSV Template
                </a>
            </div>

            {{-- Upload Form --}}
            <form method="POST" action="{{ route('school.students.import.preview') }}" enctype="multipart/form-data">
                @csrf
                @if($errors->any())
                    <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-3 mb-4">
                        <ul class="list-disc list-inside text-sm text-red-400 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-5">
                    <label class="block text-xs font-medium text-slate-400 mb-2">CSV File</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv" required
                           class="block w-full text-sm text-slate-400
                                  file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                  file:text-sm file:font-medium file:bg-slate-800 file:text-white
                                  hover:file:bg-slate-700 file:cursor-pointer file:transition">
                </div>

                <button type="submit"
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Preview Import
                </button>
            </form>

            {{-- Column Guide --}}
            <div class="mt-6 pt-6 border-t border-slate-800">
                <p class="text-xs font-semibold text-slate-500 uppercase mb-2">Column Reference</p>
                <div class="space-y-1">
                    <p class="text-xs text-slate-400"><span class="font-medium text-slate-300">Required:</span> first_name, last_name, gender</p>
                    <p class="text-xs text-slate-400"><span class="font-medium text-slate-300">Optional:</span> first_name_am, last_name_am, date_of_birth, grade, section, blood_type, status, medical_notes, guardian_name, guardian_phone, guardian_relationship</p>
                    <p class="text-xs text-slate-500">grade + section must match an existing active class (e.g. grade <code>Grade 5</code>, section <code>A</code>).</p>
                </div>
            </div>
        </div>
    </div>

@else
    {{-- Step 2: Preview --}}
    @php
        $validCount   = collect($preview)->where('valid', true)->count();
        $invalidCount = collect($preview)->where('valid', false)->count();
    @endphp

    {{-- Summary Bar --}}
    <div class="flex items-center gap-4 mb-4">
        <span class="text-sm text-slate-400">
            <span class="font-semibold text-green-400">{{ $validCount }} valid</span> /
            <span class="font-semibold text-white">{{ count($preview) }}</span> rows
            @if($invalidCount > 0)
                / <span class="font-semibold text-red-400">{{ $invalidCount }} with errors</span>
            @endif
        </span>
    </div>

    @if($invalidCount > 0)
        <div class="bg-orange-500/10 border border-orange-500/30 rounded-xl p-4 mb-5">
            <p class="text-sm text-orange-400">Fix errors below before importing — only valid rows will be imported.</p>
        </div>
    @endif

    {{-- Preview Table --}}
    <div class="bg-dark-900 rounded-xl border border-slate-800 overflow-hidden mb-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-800 text-left">
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Row #</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase">First Name</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Last Name</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Gender</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Class</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @foreach($preview as $row)
                        <tr class="{{ $row['valid'] ? 'hover:bg-slate-800/30' : 'bg-red-900/20 hover:bg-red-900/30' }} transition">
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 text-white">{{ $row['data']['first_name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-white">{{ $row['data']['last_name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $row['data']['gender'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ trim(($row['data']['grade'] ?? '') . ' ' . ($row['data']['section'] ?? '')) ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if($row['valid'])
                                    <span class="text-xs text-green-400 font-medium">&#10003; Valid</span>
                                @else
                                    <div>
                                        @foreach($row['errors'] as $error)
                                            <p class="text-xs text-red-400">{{ $error }}</p>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-3">
        @if($validCount > 0)
            <form method="POST" action="{{ route('school.students.import.confirm') }}">
                @csrf
                <input type="hidden" name="tmp_key" value="{{ $tmpKey }}">
                <button type="submit"
                        class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                    Import {{ $validCount }} valid row{{ $validCount !== 1 ? 's' : '' }}
                </button>
            </form>
        @endif
        <a href="{{ route('school.students.import.form') }}"
           class="text-sm text-slate-500 hover:text-slate-300 transition">
            Cancel / Re-upload
        </a>
    </div>
@endif
@endsection
