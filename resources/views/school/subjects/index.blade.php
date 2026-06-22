@extends('layouts.school')

@section('title', 'Subjects')
@section('heading', 'Subjects')
@section('subheading', 'Manage school subjects')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Subject List --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">All Subjects</h3>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($subjects as $subject)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full flex-shrink-0" style="background: {{ $subject->color ?? '#6366f1' }}"></div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $subject->name }}</p>
                                @if($subject->code)
                                    <p class="text-xs text-gray-400 font-mono">{{ $subject->code }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $subject->is_active ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400' }}">
                                {{ $subject->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            @if(auth()->user()->isSchoolAdmin())
                            <button type="button"
                                    class="js-edit-subject text-gray-400 hover:text-indigo-500 text-xs transition"
                                    data-id="{{ $subject->id }}"
                                    data-name="{{ $subject->name }}"
                                    data-code="{{ $subject->code }}"
                                    data-color="{{ $subject->color ?? '#6366f1' }}"
                                    data-active="{{ $subject->is_active ? '1' : '0' }}">Edit</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-gray-400 text-sm">No subjects yet. Add one →</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Add / Edit Form --}}
    @if(auth()->user()->isSchoolAdmin())
    <div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5" id="subject-form-card">
            <h3 class="text-sm font-semibold text-gray-900 mb-4" id="form-title">Add Subject</h3>

            {{-- Add Form --}}
            <form method="POST" action="{{ route('school.subjects.store') }}" id="add-form">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Subject Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Mathematics"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Code *</label>
                        <input type="text" name="code" value="{{ old('code') }}" required placeholder="e.g. MATH"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono text-gray-800 focus:outline-none focus:border-indigo-400">
                        @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Color</label>
                        <input type="color" name="color" value="{{ old('color', '#6366f1') }}"
                               class="w-full h-9 border border-gray-200 rounded-lg cursor-pointer">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-medium transition">
                        Add Subject
                    </button>
                </div>
            </form>

            {{-- Edit Form (hidden by default) --}}
            <form method="POST" action="" id="edit-form" class="hidden">
                @csrf
                @method('PUT')
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Subject Name *</label>
                        <input type="text" name="name" id="edit-name" required
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-indigo-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Code *</label>
                        <input type="text" name="code" id="edit-code" required
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono text-gray-800 focus:outline-none focus:border-indigo-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Color</label>
                        <input type="color" name="color" id="edit-color"
                               class="w-full h-9 border border-gray-200 rounded-lg cursor-pointer">
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" id="edit-active"
                                   class="w-4 h-4 rounded border-gray-300 text-indigo-500">
                            <span class="text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-medium transition">Update</button>
                        <button type="button" id="cancel-edit" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 rounded-lg text-sm transition">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
(function () {
    const baseUrl = @json(url('school/subjects'));
    const addForm = document.getElementById('add-form');
    const editForm = document.getElementById('edit-form');
    const title = document.getElementById('form-title');

    document.querySelectorAll('.js-edit-subject').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const d = btn.dataset;
            addForm.classList.add('hidden');
            editForm.classList.remove('hidden');
            title.textContent = 'Edit Subject';
            editForm.action = baseUrl + '/' + d.id;
            document.getElementById('edit-name').value = d.name;
            document.getElementById('edit-code').value = d.code || '';
            document.getElementById('edit-color').value = d.color || '#6366f1';
            document.getElementById('edit-active').checked = d.active === '1';
        });
    });

    const cancelBtn = document.getElementById('cancel-edit');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            editForm.classList.add('hidden');
            addForm.classList.remove('hidden');
            title.textContent = 'Add Subject';
        });
    }
})();
</script>
@endsection
