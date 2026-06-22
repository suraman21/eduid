@extends('layouts.school')

@section('title', $student->exists ? 'Edit Student' : 'Add Student')
@section('heading', $student->exists ? 'Edit Student' : 'Add Student')
@section('subheading', $student->exists ? $student->full_name : 'Register a new student')

@section('content')
<div class="max-w-2xl">
    <form method="POST"
          action="{{ $student->exists ? route('school.students.update', $student) : route('school.students.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if($student->exists) @method('PUT') @endif

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">

            {{-- Photo --}}
            <div class="p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Photo</h3>
                <div class="flex items-center gap-5">
                    <div class="w-20 h-20 rounded-xl bg-gray-100 flex items-center justify-center overflow-hidden" id="photo-preview">
                        @if($student->photo_url)
                            <img src="{{ $student->photo_url }}" class="w-full h-full object-cover" alt="">
                        @else
                            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        @endif
                    </div>
                    <div>
                        <label class="cursor-pointer bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm transition inline-block">
                            Choose Photo
                            <input type="file" name="photo" accept="image/*" class="hidden" onchange="previewPhoto(this)">
                        </label>
                        <p class="text-xs text-gray-400 mt-1">JPG, PNG up to 2MB</p>
                        @error('photo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Name --}}
            <div class="p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Name</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">First Name (English) *</label>
                        <input type="text" name="first_name" value="{{ old('first_name', $student->first_name) }}" required
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                        @error('first_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Last Name (English) *</label>
                        <input type="text" name="last_name" value="{{ old('last_name', $student->last_name) }}" required
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                        @error('last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">First Name (Amharic)</label>
                        <input type="text" name="first_name_am" value="{{ old('first_name_am', $student->first_name_am) }}" dir="auto"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Last Name (Amharic)</label>
                        <input type="text" name="last_name_am" value="{{ old('last_name_am', $student->last_name_am) }}" dir="auto"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                    </div>
                </div>
            </div>

            {{-- Personal --}}
            <div class="p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Personal Information</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Gender *</label>
                        <select name="gender" required class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400 bg-white">
                            <option value="">Select...</option>
                            <option value="male" {{ old('gender', $student->gender) === 'male' ? 'selected' : '' }}>Male</option>
                            <option value="female" {{ old('gender', $student->gender) === 'female' ? 'selected' : '' }}>Female</option>
                        </select>
                        @error('gender') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $student->date_of_birth?->format('Y-m-d')) }}"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Blood Type</label>
                        <select name="blood_type" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400 bg-white">
                            <option value="">Unknown</option>
                            @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt)
                                <option value="{{ $bt }}" {{ old('blood_type', $student->blood_type) === $bt ? 'selected' : '' }}>{{ $bt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select name="status" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400 bg-white">
                            @foreach(['active'=>'Active','promoted'=>'Promoted','repeating'=>'Repeating','graduated'=>'Graduated','transferred'=>'Transferred','withdrawn'=>'Withdrawn'] as $val => $label)
                                <option value="{{ $val }}" {{ old('status', $student->status ?? 'active') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Medical Notes</label>
                        <textarea name="medical_notes" rows="2"
                                  class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400 resize-none">{{ old('medical_notes', $student->medical_notes) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Academic --}}
            <div class="p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Academic</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Class</label>
                        <select name="class_id" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400 bg-white">
                            <option value="">No class assigned</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}" {{ old('class_id', $student->class_id) == $class->id ? 'selected' : '' }}>
                                    {{ $class->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Enrollment Date</label>
                        <input type="date" name="enrollment_date" value="{{ old('enrollment_date', $student->enrollment_date?->format('Y-m-d')) }}"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Join Date</label>
                        <input type="date" name="join_date" value="{{ old('join_date', $student->join_date?->format('Y-m-d')) }}"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">End Date</label>
                        <input type="date" name="end_date" value="{{ old('end_date', $student->end_date?->format('Y-m-d')) }}"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                    </div>
                </div>
            </div>

            {{-- Guardians (inline, with sibling reuse via typeahead) --}}
            <div class="p-6">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-sm font-semibold text-gray-900">Guardians / Parents</h3>
                    <button type="button" id="add-guardian-btn"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-800">+ Add another</button>
                </div>
                <p class="text-xs text-gray-400 mb-4">
                    Start typing a parent's name or phone — if they already exist (e.g. a sibling's parent),
                    pick them from the list and the details fill in automatically.
                </p>

                @if($student->exists && $student->guardians->isNotEmpty())
                    <div class="mb-4 bg-gray-50 rounded-lg p-3 text-xs text-gray-500">
                        <span class="font-medium text-gray-600">Already linked:</span>
                        {{ $student->guardians->pluck('full_name')->join(', ') }}.
                        Edit or remove these on the <a href="{{ route('school.students.show', $student) }}" class="text-indigo-600 hover:underline">student profile</a>.
                    </div>
                @endif

                <div id="guardians-list" class="space-y-3"></div>
            </div>
        </div>

        <div class="flex items-center gap-3 mt-6">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                {{ $student->exists ? 'Update Student' : 'Add Student' }}
            </button>
            <a href="{{ route('school.students.index') }}" class="text-gray-400 hover:text-gray-700 px-4 py-2.5 text-sm transition">Cancel</a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
{{-- Guardian row template (cloned by JS). __IDX__ is replaced per row. --}}
<template id="guardian-row-template">
    <div class="guardian-row border border-gray-100 rounded-lg p-3 bg-gray-50/50">
        <input type="hidden" name="guardians[__IDX__][id]" class="g-id">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="relative sm:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">Name *</label>
                <input type="text" name="guardians[__IDX__][full_name]" class="g-name w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400"
                       placeholder="Search existing or type a new name" autocomplete="off">
                <div class="g-suggestions hidden absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto"></div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Phone</label>
                <input type="text" name="guardians[__IDX__][phone]" class="g-phone w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Alt. Phone</label>
                <input type="text" name="guardians[__IDX__][phone_alt]" class="g-phonealt w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Email</label>
                <input type="email" name="guardians[__IDX__][email]" class="g-email w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Relationship</label>
                <select name="guardians[__IDX__][relationship]" class="g-rel w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
                    <option value="father">Father</option>
                    <option value="mother">Mother</option>
                    <option value="guardian" selected>Guardian</option>
                    <option value="sibling">Sibling</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="sm:col-span-2 flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="radio" name="primary_guardian" value="__IDX__" class="g-primary text-indigo-600 border-gray-300">
                    Primary contact
                </label>
                <button type="button" class="g-remove text-xs text-gray-400 hover:text-red-500">Remove</button>
            </div>
        </div>
        <p class="g-reuse-note hidden mt-2 text-xs text-emerald-600">↺ Linked to an existing guardian — no need to retype.</p>
    </div>
</template>

<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('photo-preview');
            preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

(function () {
    const SEARCH_URL = '{{ route('school.guardians.search') }}';
    const list = document.getElementById('guardians-list');
    const tpl = document.getElementById('guardian-row-template');
    let counter = 0;

    function addRow(data) {
        const idx = counter++;
        const html = tpl.innerHTML.replace(/__IDX__/g, idx);
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;

        if (data) {
            if (data.id)            row.querySelector('.g-id').value = data.id;
            if (data.full_name)     row.querySelector('.g-name').value = data.full_name;
            if (data.phone)         row.querySelector('.g-phone').value = data.phone;
            if (data.phone_alt)     row.querySelector('.g-phonealt').value = data.phone_alt;
            if (data.email)         row.querySelector('.g-email').value = data.email;
            if (data.relationship)  row.querySelector('.g-rel').value = data.relationship;
        }

        wireRow(row);
        list.appendChild(row);
        return row;
    }

    function wireRow(row) {
        const nameInput = row.querySelector('.g-name');
        const idInput   = row.querySelector('.g-id');
        const box       = row.querySelector('.g-suggestions');
        const reuseNote = row.querySelector('.g-reuse-note');
        let timer = null;

        row.querySelector('.g-remove').addEventListener('click', () => row.remove());

        // Typing a name clears any previous "existing" link (it's now edited).
        nameInput.addEventListener('input', () => {
            idInput.value = '';
            reuseNote.classList.add('hidden');

            const q = nameInput.value.trim();
            clearTimeout(timer);
            if (q.length < 2) { hide(box); return; }

            timer = setTimeout(() => {
                fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(results => render(results, row, box, reuseNote))
                .catch(() => hide(box));
            }, 220);
        });

        // Also search when typing in phone (sibling reuse by number).
        const phoneInput = row.querySelector('.g-phone');
        phoneInput.addEventListener('input', () => {
            const q = phoneInput.value.trim();
            clearTimeout(timer);
            if (q.length < 3) { hide(box); return; }
            timer = setTimeout(() => {
                fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(results => render(results, row, box, reuseNote))
                .catch(() => hide(box));
            }, 220);
        });

        document.addEventListener('click', (e) => {
            if (!row.contains(e.target)) hide(box);
        });
    }

    function render(results, row, box, reuseNote) {
        if (!results || results.length === 0) { hide(box); return; }
        box.innerHTML = '';
        results.forEach(g => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'w-full text-left px-3 py-2 hover:bg-indigo-50 border-b border-gray-50 last:border-0';
            const sib = g.students_count > 0
                ? `<span class="text-xs text-gray-400"> · ${g.students_count} child${g.students_count > 1 ? 'ren' : ''}</span>`
                : '';
            item.innerHTML =
                `<div class="text-sm text-gray-800">${escapeHtml(g.full_name)}${sib}</div>` +
                `<div class="text-xs text-gray-400">${escapeHtml(g.phone || '—')} · ${escapeHtml(g.relationship || '')}</div>`;
            item.addEventListener('click', () => {
                row.querySelector('.g-id').value = g.id;
                row.querySelector('.g-name').value = g.full_name || '';
                row.querySelector('.g-phone').value = g.phone || '';
                row.querySelector('.g-phonealt').value = g.phone_alt || '';
                row.querySelector('.g-email').value = g.email || '';
                if (g.relationship) row.querySelector('.g-rel').value = g.relationship;
                reuseNote.classList.remove('hidden');
                hide(box);
            });
            box.appendChild(item);
        });
        box.classList.remove('hidden');
    }

    function hide(box) { box.classList.add('hidden'); box.innerHTML = ''; }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }

    document.getElementById('add-guardian-btn').addEventListener('click', () => addRow());

    // Repopulate after a validation error, otherwise start with one empty row
    // on create (so guardian entry is right there, zero extra clicks).
    const old = @json(old('guardians', []));
    const oldPrimary = @json(old('primary_guardian'));
    const oldRows = Object.values(old || {});
    if (oldRows.length) {
        oldRows.forEach((g, i) => {
            const row = addRow(g);
            if (oldPrimary !== null && parseInt(oldPrimary) === i) {
                row.querySelector('.g-primary').checked = true;
            }
        });
    } else if (!{{ $student->exists ? 'true' : 'false' }}) {
        const first = addRow();
        first.querySelector('.g-primary').checked = true;
    }
})();
</script>
@endsection
