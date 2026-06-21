@extends('layouts.admin')

@section('title', $school->exists ? 'Edit School' : 'Add School')
@section('heading', $school->exists ? 'Edit School' : 'Add School')
@section('subheading', $school->exists ? $school->name : 'Register a new school')

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ $school->exists ? route('admin.schools.update', $school) : route('admin.schools.store') }}">
        @csrf
        @if($school->exists) @method('PUT') @endif

        <div class="bg-dark-900 rounded-xl border border-slate-800 divide-y divide-slate-800">
            {{-- Basic Info --}}
            <div class="p-6">
                <h3 class="text-sm font-semibold text-white mb-4">Basic Information</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">School Name *</label>
                        <input type="text" name="name" value="{{ old('name', $school->name) }}" required
                               class="w-full bg-dark-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary-500">
                        @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">School Code *</label>
                        <input type="text" name="code" value="{{ old('code', $school->code) }}" required maxlength="20"
                               class="w-full bg-dark-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white font-mono focus:outline-none focus:border-primary-500"
                               {{ $school->exists && $school->code_locked ? 'readonly' : '' }}>
                        @error('code') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Calendar System *</label>
                        <select name="calendar_system" required class="w-full bg-dark-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary-500">
                            <option value="ethiopian" {{ old('calendar_system', $school->calendar_system) === 'ethiopian' ? 'selected' : '' }}>Ethiopian</option>
                            <option value="gregorian" {{ old('calendar_system', $school->calendar_system) === 'gregorian' ? 'selected' : '' }}>Gregorian</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $school->email) }}"
                               class="w-full bg-dark-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $school->phone) }}"
                               class="w-full bg-dark-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Address</label>
                        <textarea name="address" rows="2"
                                  class="w-full bg-dark-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary-500 resize-none">{{ old('address', $school->address) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Plan & Limits --}}
            <div class="p-6">
                <h3 class="text-sm font-semibold text-white mb-4">Plan & Limits</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Plan Tier *</label>
                        <select name="plan_tier" required class="w-full bg-dark-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary-500">
                            <option value="starter" {{ old('plan_tier', $school->plan_tier) === 'starter' ? 'selected' : '' }}>Starter</option>
                            <option value="standard" {{ old('plan_tier', $school->plan_tier) === 'standard' ? 'selected' : '' }}>Standard</option>
                            <option value="premium" {{ old('plan_tier', $school->plan_tier) === 'premium' ? 'selected' : '' }}>Premium</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Max Students *</label>
                        <input type="number" name="max_students" value="{{ old('max_students', $school->max_students ?? 500) }}" required min="1"
                               class="w-full bg-dark-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Max Teachers *</label>
                        <input type="number" name="max_teachers" value="{{ old('max_teachers', $school->max_teachers ?? 50) }}" required min="1"
                               class="w-full bg-dark-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary-500">
                    </div>
                </div>
            </div>

            {{-- Subscription (edit only) --}}
            @if($school->exists)
            <div class="p-6">
                <h3 class="text-sm font-semibold text-white mb-4">Subscription</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                        <select name="subscription_status" class="w-full bg-dark-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary-500">
                            <option value="active" {{ $school->subscription_status === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="grace" {{ $school->subscription_status === 'grace' ? 'selected' : '' }}>Grace Period</option>
                            <option value="expired" {{ $school->subscription_status === 'expired' ? 'selected' : '' }}>Expired</option>
                            <option value="suspended" {{ $school->subscription_status === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Active</label>
                        <label class="flex items-center gap-2 mt-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ $school->is_active ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-slate-600 bg-dark-950 text-primary-500 focus:ring-primary-500 focus:ring-offset-0">
                            <span class="text-sm text-slate-300">School is active</span>
                        </label>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 mt-6">
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                {{ $school->exists ? 'Update School' : 'Create School' }}
            </button>
            <a href="{{ route('admin.schools.index') }}" class="text-slate-400 hover:text-white px-4 py-2.5 text-sm transition">Cancel</a>
        </div>
    </form>
</div>
@endsection
