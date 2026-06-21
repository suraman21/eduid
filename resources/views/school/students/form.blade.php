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
                            <option value="active" {{ old('status', $student->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="graduated" {{ old('status', $student->status) === 'graduated' ? 'selected' : '' }}>Graduated</option>
                            <option value="withdrawn" {{ old('status', $student->status) === 'withdrawn' ? 'selected' : '' }}>Withdrawn</option>
                            <option value="transferred" {{ old('status', $student->status) === 'transferred' ? 'selected' : '' }}>Transferred</option>
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
</script>
@endsection
