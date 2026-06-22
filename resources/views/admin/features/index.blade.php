@extends('layouts.admin')

@section('title', 'Feature Flags')
@section('heading', 'Feature Flags')
@section('subheading', 'Plan defaults and per-school overrides')

@section('content')
{{-- Section A: Plan Feature Matrix --}}
<div class="bg-dark-900 rounded-xl border border-slate-800 overflow-hidden mb-8">
    <div class="px-5 py-4 border-b border-slate-800">
        <h3 class="text-sm font-semibold text-white">Plan Feature Matrix</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-800 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Feature</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase text-center">Starter</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase text-center">Standard</th>
                    <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase text-center">Premium</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                @php $grouped = $features->groupBy('category'); @endphp
                @foreach($grouped as $category => $categoryFeatures)
                    <tr class="bg-slate-800/20">
                        <td colspan="4" class="px-5 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ $category }}</td>
                    </tr>
                    @foreach($categoryFeatures as $feature)
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="px-5 py-3">
                                <p class="text-white text-sm">{{ $feature->label }}</p>
                                <p class="text-xs text-slate-500 font-mono">{{ $feature->key }}</p>
                            </td>
                            @foreach(['starter', 'standard', 'premium'] as $tier)
                                @php
                                    $enabled = $planFeats->get($tier)?->firstWhere('feature_key', $feature->key)?->is_enabled ?? false;
                                @endphp
                                <td class="px-5 py-3 text-center">
                                    @if($enabled)
                                        <svg class="w-5 h-5 text-green-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    @else
                                        <span class="text-slate-700">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Section B: Per-School Overrides --}}
<div class="bg-dark-900 rounded-xl border border-slate-800 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between gap-4">
        <h3 class="text-sm font-semibold text-white">Per-School Overrides</h3>
        <form method="GET" class="flex items-center gap-2">
            <select name="school_id" class="bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
                <option value="">Select a school…</option>
                @foreach($schools as $school)
                    <option value="{{ $school->id }}" {{ (isset($selectedSchool) && $selectedSchool->id == $school->id) ? 'selected' : '' }}>
                        {{ $school->name }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm transition">View</button>
        </form>
    </div>

    @if(isset($selectedSchool))
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-800 text-left">
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Feature</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Plan Default</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Override</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @foreach($features as $feature)
                        @php
                            $planDefault = $planFeats->get($selectedSchool->plan_tier)?->firstWhere('feature_key', $feature->key)?->is_enabled ?? false;
                            $override = $schoolOverrides->firstWhere('feature_key', $feature->key);
                        @endphp
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="px-5 py-3">
                                <p class="text-white">{{ $feature->label }}</p>
                                <p class="text-xs text-slate-500 font-mono">{{ $feature->key }}</p>
                            </td>
                            <td class="px-5 py-3">
                                @if($planDefault)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-500/10 text-green-400">Enabled</span>
                                @else
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-400">Disabled</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if($override)
                                    <div class="flex items-center gap-3">
                                        @if($override->is_enabled)
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-500/10 text-green-400">Enabled</span>
                                        @else
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-red-500/10 text-red-400">Disabled</span>
                                        @endif
                                        <form method="POST" action="{{ route('admin.features.override.remove', [$selectedSchool, $feature->key]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-slate-500 hover:text-red-400 transition">Remove</button>
                                        </form>
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('admin.features.override', $selectedSchool) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="feature_key" value="{{ $feature->key }}">
                                        <select name="is_enabled" class="bg-dark-950 border border-slate-700 rounded-lg px-2 py-1 text-xs text-slate-300 focus:outline-none focus:border-primary-500">
                                            <option value="1">Enable</option>
                                            <option value="0">Disable</option>
                                        </select>
                                        <button type="submit" class="text-xs bg-slate-800 hover:bg-slate-700 text-white px-2 py-1 rounded-lg transition">Set</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="px-5 py-12 text-center text-slate-500">
            <p class="text-sm">Select a school above to view and manage overrides.</p>
        </div>
    @endif
</div>
@endsection
