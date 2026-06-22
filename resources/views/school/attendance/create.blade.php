@extends('layouts.school')

@section('title', 'Take Attendance')
@section('heading', 'Take Attendance')
@section('subheading', 'Select a class and mark each student')

@section('content')
{{-- Step 1: choose class / date / session. Submitting reloads with the roster. --}}
<form method="GET" action="{{ route('school.attendance.create') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
            <select name="class_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
                <option value="">Select class…</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" {{ $class && $class->id === $c->id ? 'selected' : '' }}>{{ $c->display_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" name="session_date" value="{{ $sessionDate }}" max="{{ now()->toDateString() }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Session</label>
            <select name="session_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
                @foreach(['morning'=>'Morning','afternoon'=>'Afternoon','full_day'=>'Full Day'] as $val => $label)
                    <option value="{{ $val }}" {{ $sessionType === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Load Roster</button>
        </div>
    </div>
</form>

@if($class)
    @if($roster->isEmpty())
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
            <p>No active students in {{ $class->display_name }}.</p>
            <a href="{{ route('school.students.index') }}" class="text-indigo-500 hover:text-indigo-600 text-sm mt-1 inline-block">Manage students &rarr;</a>
        </div>
    @else
        {{-- Step 2: mark the roster. --}}
        <form method="POST" action="{{ route('school.attendance.store') }}" x-data="{}">
            @csrf
            <input type="hidden" name="class_id" value="{{ $class->id }}">
            <input type="hidden" name="session_date" value="{{ $sessionDate }}">
            <input type="hidden" name="session_type" value="{{ $sessionType }}">

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <div>
                        <p class="font-medium text-gray-900">{{ $class->display_name }}</p>
                        <p class="text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($sessionDate)->format('M d, Y') }} · {{ ucfirst(str_replace('_',' ',$sessionType)) }} · {{ $roster->count() }} students</p>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-gray-400">Mark all:</span>
                        <button type="button" data-markall="present" class="markall px-2 py-1 rounded bg-green-50 text-green-600 hover:bg-green-100">Present</button>
                        <button type="button" data-markall="absent" class="markall px-2 py-1 rounded bg-red-50 text-red-600 hover:bg-red-100">Absent</button>
                    </div>
                </div>

                <div class="divide-y divide-gray-50">
                    @foreach($roster as $i => $student)
                        @php $current = $existing[$student->id] ?? 'present'; @endphp
                        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-center gap-3">
                            <input type="hidden" name="records[{{ $i }}][student_id]" value="{{ $student->id }}">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-500 flex-shrink-0">
                                    {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 truncate">{{ $student->full_name }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ $student->uid }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 flex-shrink-0">
                                @foreach(['present'=>'green','late'=>'amber','excused'=>'blue','absent'=>'red'] as $status => $color)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="records[{{ $i }}][status]" value="{{ $status }}" class="peer sr-only status-radio" {{ $current === $status ? 'checked' : '' }}>
                                        <span class="px-3 py-1 rounded-lg text-xs capitalize border border-gray-200 text-gray-500
                                            peer-checked:bg-{{ $color }}-50 peer-checked:text-{{ $color }}-600 peer-checked:border-{{ $color }}-300 hover:bg-gray-50 transition inline-block">
                                            {{ $status }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-3 mt-6">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">Save Attendance</button>
                <a href="{{ route('school.attendance.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>

        {{-- Bulk "mark all" without inline handlers (CSP-safe). --}}
        <script nonce="{{ $cspNonce }}">
            document.querySelectorAll('.markall').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var status = btn.getAttribute('data-markall');
                    document.querySelectorAll('.status-radio[value="' + status + '"]').forEach(function (radio) {
                        radio.checked = true;
                    });
                });
            });
        </script>
    @endif
@endif
@endsection
