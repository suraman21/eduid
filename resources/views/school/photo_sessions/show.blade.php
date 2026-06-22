@extends('layouts.school')

@section('title', 'Photo Session #' . $session->id)
@section('heading', 'Photo Session')
@section('subheading', ($session->schoolClass?->display_name ?? 'Whole School') . ' — ' . ($session->session_type === 'app_capture' ? 'Step-by-step' : 'ZIP Upload'))

@section('content')

@if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('info'))
    <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg text-sm">{{ session('info') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
@endif

{{-- Status banner for completed/cancelled --}}
@if(! $session->isActive())
    <div class="mb-6 bg-{{ $session->status === 'completed' ? 'blue' : 'gray' }}-50 border border-{{ $session->status === 'completed' ? 'blue' : 'gray' }}-200 rounded-xl px-5 py-4 flex items-center justify-between">
        <div>
            <p class="font-medium text-{{ $session->status === 'completed' ? 'blue' : 'gray' }}-800">
                Session {{ ucfirst($session->status) }}
            </p>
            <p class="text-sm text-{{ $session->status === 'completed' ? 'blue' : 'gray' }}-600 mt-0.5">
                {{ $session->photos_captured }} of {{ $session->total_students }} photos captured
            </p>
        </div>
        <a href="{{ route('school.photo-sessions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">← Back to sessions</a>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: Current action area --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Progress card --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">Progress</h3>
                <span class="text-sm text-gray-500">{{ $session->photos_captured }} / {{ $session->total_students }} photos</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2">
                <div class="bg-indigo-500 h-2 rounded-full transition-all" style="width: {{ $session->progress }}%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ number_format($session->progress, 0) }}% complete</p>
        </div>

        @if($session->isActive())

            @if($session->session_type === 'app_capture')
                {{-- Step-by-step: current student + upload --}}
                @if($currentStudent)
                    <div class="bg-white rounded-xl border border-indigo-100 shadow-sm p-6">
                        <p class="text-xs font-medium text-indigo-500 uppercase tracking-wide mb-1">
                            Student {{ $session->current_position + 1 }} of {{ $session->total_students }}
                        </p>
                        <div class="flex items-center gap-4 mb-5">
                            <div class="w-16 h-16 rounded-full bg-gray-100 overflow-hidden flex items-center justify-center flex-shrink-0">
                                @if($currentStudent->photo_path)
                                    <img src="{{ $currentStudent->photo_url }}" alt="{{ $currentStudent->full_name }}" class="w-full h-full object-cover">
                                @else
                                    <svg class="w-8 h-8 text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">{{ $currentStudent->full_name }}</h2>
                                <p class="text-sm text-gray-400">{{ $currentStudent->uid }} · {{ $currentStudent->schoolClass?->display_name ?? 'No class' }}</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('school.photo-sessions.upload-photo', $session) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Upload photo for this student</label>
                                <input type="file" name="photo" accept="image/*" required
                                       class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                @error('photo')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                                    Save &amp; Next →
                                </button>
                                <form method="POST" action="{{ route('school.photo-sessions.skip', $session) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-sm text-gray-400 hover:text-gray-600 transition">Skip this student</button>
                                </form>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
                        <svg class="w-10 h-10 text-green-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="font-semibold text-green-800">All students processed!</p>
                        <p class="text-sm text-green-600 mt-1">Click below to mark this session as complete.</p>
                        <form method="POST" action="{{ route('school.photo-sessions.complete', $session) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                                Complete Session
                            </button>
                        </form>
                    </div>
                @endif

            @else
                {{-- ZIP Upload mode --}}
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Upload ZIP File</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Name your photos <code class="bg-gray-100 px-1 rounded">001.jpg</code>, <code class="bg-gray-100 px-1 rounded">002.jpg</code>… matching the alphabetical student order shown in the queue. Then zip them and upload here.
                    </p>
                    <form method="POST" action="{{ route('school.photo-sessions.upload-zip', $session) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <input type="file" name="zip_file" accept=".zip" required
                                   class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            @error('zip_file')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                            Upload &amp; Match
                        </button>
                    </form>
                </div>
            @endif

            {{-- Complete / Cancel controls --}}
            <div class="flex items-center gap-4">
                <form method="POST" action="{{ route('school.photo-sessions.complete', $session) }}">
                    @csrf
                    <button type="submit" class="text-sm text-green-600 hover:text-green-800 font-medium">Mark complete</button>
                </form>
                <form method="POST" action="{{ route('school.photo-sessions.cancel', $session) }}"
                      onsubmit="return confirm('Cancel this session? Progress is kept but the session will close.')">
                    @csrf
                    <button type="submit" class="text-sm text-red-400 hover:text-red-600">Cancel session</button>
                </form>
            </div>

        @endif
    </div>

    {{-- Right: Student queue --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">Student Queue</h3>
            <p class="text-xs text-gray-400 mt-0.5">Alphabetical order</p>
        </div>
        <ul class="divide-y divide-gray-50 max-h-[520px] overflow-y-auto">
            @foreach($session->student_queue as $pos => $studentId)
                @php $stu = $queueStudents->get($studentId); @endphp
                <li class="px-4 py-2.5 flex items-center gap-3 {{ $pos === $session->current_position && $session->isActive() ? 'bg-indigo-50' : '' }}">
                    <span class="text-xs text-gray-300 w-5 text-right flex-shrink-0">{{ $pos + 1 }}</span>
                    <div class="w-7 h-7 rounded-full overflow-hidden bg-gray-100 flex-shrink-0 flex items-center justify-center">
                        @if($stu?->photo_path)
                            <img src="{{ $stu->photo_url }}" alt="" class="w-full h-full object-cover">
                        @else
                            <svg class="w-3.5 h-3.5 text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                            </svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-700 truncate {{ $pos === $session->current_position && $session->isActive() ? 'font-semibold text-indigo-700' : '' }}">
                            {{ $stu?->full_name ?? 'Unknown' }}
                        </p>
                        <p class="text-xs text-gray-400 truncate">{{ $stu?->uid }}</p>
                    </div>
                    @if($pos < $session->current_position || $stu?->photo_path)
                        <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    @elseif($pos === $session->current_position && $session->isActive())
                        <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"></span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

</div>

@endsection
