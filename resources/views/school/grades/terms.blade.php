@extends('layouts.school')

@section('title', 'Terms')
@section('heading', 'Academic Terms')
@section('subheading', 'Manage term definitions and weights')

@section('content')

@if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Create term form --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Add Term</h3>
        <form method="POST" action="{{ route('school.grades.terms.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Term 1"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400" required>
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                <input type="text" name="academic_year" value="{{ old('academic_year') }}" placeholder="e.g. 2018 E.C."
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400" required>
                @error('academic_year')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400" required>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Order</label>
                    <input type="number" name="order" value="{{ old('order', $terms->count() + 1) }}" min="1" max="10"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Weight (%)</label>
                    <input type="number" name="weight" value="{{ old('weight') }}" min="0" max="100" step="0.01" placeholder="Optional"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                </div>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_current" value="1" {{ old('is_current') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                <span class="text-sm text-gray-700">Mark as current term</span>
            </label>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">Create Term</button>
        </form>
    </div>

    {{-- Terms list --}}
    <div class="lg:col-span-2">
        @if($terms->isEmpty())
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
                <p>No terms yet. Create the first one.</p>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-left">Term</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-left">Year</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-center">Order</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-center">Weight</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($terms as $t)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-5 py-3 font-medium text-gray-900">{{ $t->name }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $t->academic_year }}</td>
                                <td class="px-5 py-3 text-center text-gray-500">{{ $t->order }}</td>
                                <td class="px-5 py-3 text-center text-gray-500">{{ $t->weight !== null ? $t->weight . '%' : '—' }}</td>
                                <td class="px-5 py-3 text-center">
                                    @if($t->is_current)
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-600">Current</span>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('school.grades.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to Grades</a>
</div>

@endsection
