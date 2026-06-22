@extends('layouts.school')

@section('title', $student->full_name)
@section('heading', $student->full_name)
@section('subheading', 'UID: ' . $student->uid)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Main --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-start justify-between mb-5">
                <div class="flex items-center gap-4">
                    @if($student->photo_url)
                        <img src="{{ $student->photo_url }}" class="w-20 h-20 rounded-xl object-cover" alt="">
                    @else
                        <div class="w-20 h-20 rounded-xl bg-indigo-50 flex items-center justify-center text-2xl font-bold text-indigo-400">
                            {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">{{ $student->full_name }}</h2>
                        @if($student->full_name_am)
                            <p class="text-sm text-gray-500">{{ $student->full_name_am }}</p>
                        @endif
                        <p class="text-xs text-gray-400 mt-1 font-mono">{{ $student->uid }}</p>
                    </div>
                </div>
                @if(auth()->user()->isSchoolAdmin())
                <a href="{{ route('school.students.edit', $student) }}" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">Edit &rarr;</a>
                @endif
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs text-gray-400">Gender</dt>
                    <dd class="text-gray-800 capitalize mt-0.5">{{ $student->gender }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">Date of Birth</dt>
                    <dd class="text-gray-800 mt-0.5">{{ $student->date_of_birth?->format('M d, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">Blood Type</dt>
                    <dd class="text-gray-800 mt-0.5">{{ $student->blood_type ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">Status</dt>
                    <dd class="mt-0.5">
                        @php $statusColors = ['active'=>'bg-green-50 text-green-600','promoted'=>'bg-emerald-50 text-emerald-600','repeating'=>'bg-orange-50 text-orange-600','graduated'=>'bg-blue-50 text-blue-600','withdrawn'=>'bg-red-50 text-red-600','transferred'=>'bg-amber-50 text-amber-600']; @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $statusColors[$student->status ?? 'active'] ?? '' }}">{{ ucfirst($student->status ?? 'active') }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">Class</dt>
                    <dd class="text-gray-800 mt-0.5">{{ $student->schoolClass?->display_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">Enrollment Date</dt>
                    <dd class="text-gray-800 mt-0.5">{{ $student->enrollment_date?->format('M d, Y') ?? '—' }}</dd>
                </div>
                @if($student->medical_notes)
                <div class="col-span-2">
                    <dt class="text-xs text-gray-400">Medical Notes</dt>
                    <dd class="text-gray-800 mt-0.5 text-sm">{{ $student->medical_notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Guardians --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Guardians</h3>

            @if(auth()->user()->isSchoolAdmin())
                {{-- Add form (collapsed via native <details>, no JS needed) --}}
                <details class="mb-5 group">
                    <summary class="cursor-pointer list-none text-xs font-medium text-indigo-600 hover:text-indigo-800 mb-3 select-none">
                        <span class="group-open:hidden">+ Add guardian</span>
                        <span class="hidden group-open:inline">− Close</span>
                    </summary>
                    <form method="POST" action="{{ route('school.guardians.store', $student) }}"
                          class="bg-gray-50 rounded-lg p-4 space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="full_name" placeholder="Full name" required
                               class="col-span-2 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                        <input type="text" name="phone" placeholder="Phone"
                               class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                        <input type="text" name="phone_alt" placeholder="Alt. phone"
                               class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                        <input type="email" name="email" placeholder="Email (optional)"
                               class="col-span-2 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                        <select name="relationship"
                                class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
                            @foreach(['father'=>'Father','mother'=>'Mother','guardian'=>'Guardian','sibling'=>'Sibling','other'=>'Other'] as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="is_primary" value="1" class="rounded border-gray-300 text-indigo-600">
                            Primary contact
                        </label>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition">Save</button>
                    </div>
                    @error('full_name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    </form>
                </details>
            @endif

            @forelse($student->guardians as $guardian)
                <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $guardian->full_name }}</p>
                        <p class="text-xs text-gray-400">{{ $guardian->phone ?? '—' }} &middot; {{ ucfirst($guardian->relationship ?? '') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($guardian->pivot->is_primary)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600">Primary</span>
                        @endif
                        @if(auth()->user()->isSchoolAdmin())
                            <form method="POST" action="{{ route('school.guardians.destroy', [$student, $guardian]) }}"
                                  data-confirm="Remove {{ $guardian->full_name }} from this student?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-gray-300 hover:text-red-500 transition" title="Remove">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">No guardians linked</p>
            @endforelse
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Dates</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-400">Join Date</span>
                    <span class="text-gray-700">{{ $student->join_date?->format('M d, Y') ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">End Date</span>
                    <span class="text-gray-700">{{ $student->end_date?->format('M d, Y') ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Added</span>
                    <span class="text-gray-700">{{ $student->created_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">QR Token</h3>
            @if($student->qr_token)
                <p class="text-xs font-mono text-gray-500 break-all">{{ $student->qr_token }}</p>
                <p class="text-xs text-gray-400 mt-2">Year: {{ $student->qr_token_year }}</p>
                <p class="text-xs mt-1 {{ $student->hasValidQrToken() ? 'text-green-500' : 'text-amber-500' }}">
                    {{ $student->hasValidQrToken() ? 'Valid' : 'Expired — needs refresh' }}
                </p>
            @else
                <p class="text-sm text-gray-400">No QR token generated</p>
            @endif
        </div>
    </div>
</div>

<div class="mt-6">
    <a href="{{ route('school.students.index') }}" class="text-sm text-gray-400 hover:text-gray-700 transition">&larr; Back to Students</a>
</div>
@endsection
