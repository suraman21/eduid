@extends('layouts.school')

@section('title', 'Classes')
@section('heading', 'Classes')
@section('subheading', 'Manage grade levels and sections')

@section('content')
<div class="flex justify-end mb-6">
    @if(auth()->user()->isSchoolAdmin())
    <a href="{{ route('school.classes.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Class
    </a>
    @endif
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Class</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Academic Year</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Students</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($classes as $class)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center text-sm font-bold text-indigo-500">
                                    {{ substr($class->grade, 0, 2) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $class->display_name }}</p>
                                    <p class="text-xs text-gray-400">Grade {{ $class->grade }} &middot; Section {{ $class->section }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $class->academic_year }}</td>
                        <td class="px-5 py-3">
                            <span class="font-medium text-gray-900">{{ $class->students_count }}</span>
                            <span class="text-gray-400 text-xs"> students</span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $class->is_active ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400' }}">
                                {{ $class->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if(auth()->user()->isSchoolAdmin())
                            <a href="{{ route('school.classes.edit', $class) }}" class="text-gray-400 hover:text-indigo-500 transition text-xs">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-gray-400">
                            <svg class="w-10 h-10 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            No classes yet. <a href="{{ route('school.classes.create') }}" class="text-indigo-500">Add one &rarr;</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
