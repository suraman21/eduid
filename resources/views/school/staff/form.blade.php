@extends('layouts.school')

@section('title', $member->exists ? 'Edit Staff' : 'Add Staff')
@section('heading', $member->exists ? 'Edit Staff Member' : 'Add Staff Member')
@section('subheading', $member->exists ? 'Update staff details' : 'Create a new staff account')

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ $member->exists ? route('school.staff.update', $member) : route('school.staff.store') }}">
        @csrf
        @if($member->exists) @method('PUT') @endif

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-400">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $member->name) }}" required
                           class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-indigo-400">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-400">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $member->email) }}" required
                           class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-indigo-400">
                    @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password {{ $member->exists ? '' : '*' }}</label>
                    <input type="password" name="password" autocomplete="new-password"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-indigo-400"
                           {{ $member->exists ? '' : 'required' }}>
                    @if($member->exists)
                        <p class="text-xs text-gray-400 mt-1">Leave blank to keep current password</p>
                    @endif
                    @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role <span class="text-red-400">*</span></label>
                    <select name="role" required class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-indigo-400 bg-white">
                        @foreach(['school_admin'=>'School Admin','teacher'=>'Teacher','staff'=>'Staff'] as $val => $label)
                            <option value="{{ $val }}" {{ old('role', $member->role) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $member->phone) }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-indigo-400">
                    @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee ID</label>
                    <input type="text" name="employee_id" value="{{ old('employee_id', $member->employee_id) }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-indigo-400">
                    @error('employee_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                <input type="text" name="job_title" value="{{ old('job_title', $member->job_title) }}" placeholder="e.g. Mathematics Teacher, Registrar"
                       class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-indigo-400">
                @error('job_title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="border-t border-gray-100 pt-5">
                <p class="text-sm font-medium text-gray-700 mb-3">Permissions</p>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="hidden" name="can_login" value="0">
                        <input type="checkbox" name="can_login" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               {{ old('can_login', $member->exists ? $member->can_login : true) ? 'checked' : '' }}>
                        Can log in to the system
                    </label>
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="hidden" name="is_homeroom" value="0">
                        <input type="checkbox" name="is_homeroom" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               {{ old('is_homeroom', $member->is_homeroom) ? 'checked' : '' }}>
                        Homeroom teacher
                    </label>
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="hidden" name="is_coordinator" value="0">
                        <input type="checkbox" name="is_coordinator" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               {{ old('is_coordinator', $member->is_coordinator) ? 'checked' : '' }}>
                        Department coordinator
                    </label>
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="hidden" name="can_record_staff_attendance" value="0">
                        <input type="checkbox" name="can_record_staff_attendance" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               {{ old('can_record_staff_attendance', $member->can_record_staff_attendance) ? 'checked' : '' }}>
                        Can record staff attendance
                    </label>
                </div>
            </div>

            @if($member->exists)
            <div class="border-t border-gray-100 pt-5">
                <label class="flex items-center gap-3 text-sm text-gray-600">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                           {{ old('is_active', $member->is_active) ? 'checked' : '' }}>
                    Account is active
                </label>
            </div>
            @endif
        </div>

        <div class="flex items-center gap-3 mt-6">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">
                {{ $member->exists ? 'Update' : 'Create' }} Staff Member
            </button>
            <a href="{{ route('school.staff.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
