@extends('layouts.school')

@section('title', 'Photo Sessions')
@section('heading', 'Photo Sessions')
@section('subheading', 'Structured workflows for photographing students')

@section('content')

@if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
@endif

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-400">{{ $sessions->total() }} session{{ $sessions->total() === 1 ? '' : 's' }}</p>
    @if(auth()->user()->isSchoolAdmin())
        <a href="{{ route('school.photo-sessions.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Session
        </a>
    @endif
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    @if($sessions->isEmpty())
        <div class="px-6 py-16 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <p class="font-medium text-gray-500">No photo sessions yet</p>
            <p class="text-sm mt-1">Start a session to photograph students and link photos to their profiles.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-left">
                        <th class="px-5 py-3 font-medium text-gray-500">Session</th>
                        <th class="px-5 py-3 font-medium text-gray-500">Type</th>
                        <th class="px-5 py-3 font-medium text-gray-500">Class</th>
                        <th class="px-5 py-3 font-medium text-gray-500">Progress</th>
                        <th class="px-5 py-3 font-medium text-gray-500">Status</th>
                        <th class="px-5 py-3 font-medium text-gray-500">Created</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($sessions as $session)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3 text-gray-700 font-medium">#{{ $session->id }}</td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $session->session_type === 'app_capture' ? 'Step-by-step' : 'ZIP Upload' }}
                        </td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $session->schoolClass?->display_name ?? 'Whole School' }}
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-24 bg-gray-100 rounded-full h-1.5">
                                    <div class="bg-indigo-500 h-1.5 rounded-full" style="width: {{ $session->progress }}%"></div>
                                </div>
                                <span class="text-gray-500 text-xs">{{ $session->photos_captured }}/{{ $session->total_students }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            @if($session->status === 'active')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                                </span>
                            @elseif($session->status === 'completed')
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">Completed</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Cancelled</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-400 text-xs">{{ $session->created_at->diffForHumans() }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('school.photo-sessions.show', $session) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                {{ $session->isActive() ? 'Continue →' : 'View →' }}
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($sessions->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">{{ $sessions->links() }}</div>
        @endif
    @endif
</div>

@endsection
