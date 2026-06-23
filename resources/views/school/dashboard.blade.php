@extends('layouts.school')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', $school->name . ' — ' . ($school->academic_year ?? date('Y')))

@section('content')
{{-- Subscription Warning --}}
@if($school->subscription_status === 'grace')
    <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
        <div>
            <p class="text-sm font-medium text-amber-800">Subscription in Grace Period</p>
            <p class="text-xs text-amber-600 mt-0.5">New data entry is restricted. Contact Arkeon to renew your subscription.</p>
        </div>
    </div>
@elseif($school->subscription_status === 'expired' || $school->subscription_status === 'suspended')
    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-start gap-3">
        <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
        <div>
            <p class="text-sm font-medium text-red-800">Subscription Expired</p>
            <p class="text-xs text-red-600 mt-0.5">Your subscription has expired. Data is read-only. Contact Arkeon to reactivate.</p>
        </div>
    </div>
@endif

{{-- Stats --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:shadow transition">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-600">{{ $stats['active_students'] }} active</span>
        </div>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_students']) }}</p>
        <p class="text-xs text-gray-400 mt-1">Students</p>
        <div class="mt-2">
            <div class="flex justify-between text-xs mb-1">
                <span class="text-gray-400">Capacity</span>
                <span class="text-gray-600">{{ $stats['total_students'] }} / {{ $school->max_students }}</span>
            </div>
            @php $pct = $school->max_students > 0 ? min(100, ($stats['total_students'] / $school->max_students) * 100) : 0; @endphp
            <div class="w-full bg-gray-100 rounded-full h-1.5">
                <div class="h-1.5 rounded-full {{ $pct > 90 ? 'bg-red-500' : ($pct > 70 ? 'bg-amber-500' : 'bg-indigo-500') }}" style="width: {{ $pct }}%"></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:shadow transition">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">{{ $stats['total_staff'] }}</p>
        <p class="text-xs text-gray-400 mt-1">Staff Members</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:shadow transition">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">{{ $stats['total_classes'] }}</p>
        <p class="text-xs text-gray-400 mt-1">Classes</p>
    </div>
</div>

{{-- Second Row --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
    <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
            <p class="text-xl font-bold text-gray-900">{{ $stats['today_sessions'] }}</p>
        </div>
        <p class="text-xs text-gray-400">Attendance Sessions Today</p>
    </div>
    <a href="{{ route('school.card-orders.index') }}" class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:border-orange-200 hover:shadow-md transition block">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1"/></svg>
            </div>
            <p class="text-xl font-bold text-gray-900">{{ $stats['pending_orders'] }}</p>
        </div>
        <p class="text-xs text-gray-400">Pending Card Orders</p>
    </a>
</div>

{{-- Recent Students --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Recently Added Students</h3>
    </div>
    <div class="divide-y divide-gray-50">
        @forelse($recentStudents as $student)
            <div class="px-5 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    @if($student->photo_path)
                        <img src="{{ Storage::url($student->photo_path) }}" class="w-8 h-8 rounded-full object-cover" alt="">
                    @else
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-400">
                            {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name ?? '', 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $student->first_name }} {{ $student->last_name }}</p>
                        <p class="text-xs text-gray-400">{{ $student->student_id ?? 'No ID' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400">{{ $student->created_at->diffForHumans() }}</span>
                    <a href="{{ route('school.students.id-card', $student) }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">ID Card</a>
                </div>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-sm text-gray-400">No students registered yet</div>
        @endforelse
    </div>
</div>

{{-- Quick Info --}}
<div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
        <p class="text-xs text-gray-400 mb-1">Plan</p>
        <p class="text-sm font-semibold text-gray-900 capitalize">{{ $school->plan_tier ?? 'Starter' }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
        <p class="text-xs text-gray-400 mb-1">Calendar</p>
        <p class="text-sm font-semibold text-gray-900 capitalize">{{ $school->calendar_system ?? 'Ethiopian' }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
        <p class="text-xs text-gray-400 mb-1">Subscription Expires</p>
        <p class="text-sm font-semibold text-gray-900">{{ $school->subscription_expires_at ? $school->subscription_expires_at->format('M d, Y') : 'N/A' }}</p>
    </div>
</div>
@endsection
