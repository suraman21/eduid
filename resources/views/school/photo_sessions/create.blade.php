@extends('layouts.school')

@section('title', 'New Photo Session')
@section('heading', 'New Photo Session')
@section('subheading', 'Set up a structured photography workflow')

@section('content')

@if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
@endif

<div class="max-w-xl">
    <form method="POST" action="{{ route('school.photo-sessions.store') }}">
        @csrf

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-6">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Class <span class="text-gray-400 font-normal">(optional — leave blank for whole school)</span></label>
                <select name="class_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 bg-white focus:outline-none focus:border-indigo-400">
                    <option value="">All classes (whole school)</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" {{ old('class_id') == $class->id ? 'selected' : '' }}>
                            {{ $class->display_name }}
                        </option>
                    @endforeach
                </select>
                @error('class_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Session Type</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative cursor-pointer">
                        <input type="radio" name="session_type" value="app_capture" class="peer sr-only" {{ old('session_type', 'app_capture') === 'app_capture' ? 'checked' : '' }}>
                        <div class="border-2 border-gray-200 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 rounded-xl p-4 transition">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="text-sm font-semibold text-gray-800">Step-by-step</span>
                            </div>
                            <p class="text-xs text-gray-500">Upload one photo per student in queue order. Best for picture day.</p>
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="session_type" value="zip_upload" class="peer sr-only" {{ old('session_type') === 'zip_upload' ? 'checked' : '' }}>
                        <div class="border-2 border-gray-200 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 rounded-xl p-4 transition">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-sm font-semibold text-gray-800">ZIP Upload</span>
                            </div>
                            <p class="text-xs text-gray-500">Upload a ZIP of sequentially-named files (001.jpg, 002.jpg…). Best for studio photos.</p>
                        </div>
                    </label>
                </div>
                @error('session_type')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-xs text-blue-700">
                <strong>How it works:</strong> The system will generate an alphabetical queue of active students.
                For step-by-step, line up students in that order and upload each photo as they step forward.
                For ZIP upload, name your files 001.jpg, 002.jpg, etc. matching the alphabetical order.
            </div>

        </div>

        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                Start Session
            </button>
            <a href="{{ route('school.photo-sessions.index') }}" class="text-sm text-gray-400 hover:text-gray-600">Cancel</a>
        </div>
    </form>
</div>

@endsection
