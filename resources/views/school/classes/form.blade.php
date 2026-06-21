@extends('layouts.school')

@section('title', $class->exists ? 'Edit Class' : 'Add Class')
@section('heading', $class->exists ? 'Edit Class' : 'Add Class')
@section('subheading', $class->exists ? $class->display_name : 'Create a new class')

@section('content')
<div class="max-w-lg">
    <form method="POST" action="{{ $class->exists ? route('school.classes.update', $class) : route('school.classes.store') }}">
        @csrf
        @if($class->exists) @method('PUT') @endif

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Grade *</label>
                    <input type="text" name="grade" value="{{ old('grade', $class->grade) }}" required
                           placeholder="e.g. Grade 9, KG1"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                    @error('grade') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Section *</label>
                    <input type="text" name="section" value="{{ old('section', $class->section) }}" required
                           placeholder="e.g. A, B, C"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                    @error('section') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Academic Year *</label>
                    <input type="text" name="academic_year" value="{{ old('academic_year', $class->academic_year ?? $currentSchool->academic_year) }}" required
                           placeholder="e.g. 2016/2017"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Sort Order</label>
                    <input type="number" name="level_order" value="{{ old('level_order', $class->level_order ?? 0) }}" min="0"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                    <p class="text-xs text-gray-400 mt-1">Lower = appears first</p>
                </div>
            </div>

            @if($class->exists)
            <div>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ $class->is_active ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-indigo-500 focus:ring-indigo-400 focus:ring-offset-0">
                    <span class="text-sm text-gray-700">Class is active</span>
                </label>
            </div>
            @endif
        </div>

        <div class="flex items-center gap-3 mt-5">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                {{ $class->exists ? 'Update Class' : 'Create Class' }}
            </button>
            <a href="{{ route('school.classes.index') }}" class="text-gray-400 hover:text-gray-700 px-4 py-2.5 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
