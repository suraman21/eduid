@extends('layouts.admin')

@section('title', 'Record Payment')
@section('heading', 'Record Payment')
@section('subheading', 'Manually record a school subscription payment')

@section('content')
<div class="max-w-2xl">
    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 mb-6">
            <p class="text-sm font-medium text-red-400 mb-2">Please fix the following errors:</p>
            <ul class="list-disc list-inside text-sm text-red-400 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-dark-900 rounded-xl border border-slate-800 p-6">
        <form method="POST" action="{{ route('admin.payments.store') }}">
            @csrf

            {{-- School --}}
            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 mb-1">School <span class="text-red-400">*</span></label>
                <select name="school_id" required class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
                    <option value="">Select school…</option>
                    @foreach($schools as $school)
                        <option value="{{ $school->id }}" {{ old('school_id') == $school->id ? 'selected' : '' }}>{{ $school->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Plan --}}
            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 mb-1">Plan</label>
                <select name="plan_id" class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
                    <option value="">No specific plan</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" {{ old('plan_id') == $plan->id ? 'selected' : '' }}>
                            {{ ucfirst($plan->tier) }} — {{ $plan->name }} (ETB {{ number_format($plan->price, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Amount --}}
            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 mb-1">Amount (ETB) <span class="text-red-400">*</span></label>
                <input type="number" name="amount_etb" value="{{ old('amount_etb') }}" required min="0" step="0.01"
                       class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
            </div>

            {{-- Payment Method --}}
            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 mb-1">Payment Method</label>
                <select name="payment_method" id="payment_method_select"
                        data-toggle-target="bank_name_row" data-toggle-value="bank_transfer"
                        class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
                    <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                    <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                    <option value="chapa" {{ old('payment_method') === 'chapa' ? 'selected' : '' }}>Chapa</option>
                    <option value="other" {{ old('payment_method') === 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            {{-- Bank Name (conditional) --}}
            <div id="bank_name_row" class="mb-4" style="display: {{ old('payment_method', 'bank_transfer') === 'bank_transfer' ? 'block' : 'none' }}">
                <label class="block text-xs font-medium text-slate-400 mb-1">Bank Name</label>
                <input type="text" name="bank_name" value="{{ old('bank_name') }}"
                       class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500"
                       placeholder="e.g. Commercial Bank of Ethiopia">
            </div>

            {{-- Reference Number --}}
            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 mb-1">Reference Number</label>
                <input type="text" name="reference_number" value="{{ old('reference_number') }}"
                       class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
            </div>

            {{-- Payment Date --}}
            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 mb-1">Payment Date</label>
                <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}"
                       class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
            </div>

            {{-- Period Months --}}
            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 mb-1">Period (Months)</label>
                <input type="number" name="period_months" value="{{ old('period_months', 1) }}" min="1" max="24"
                       class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
            </div>

            {{-- Notes --}}
            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 mb-1">Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500 resize-none">{{ old('notes') }}</textarea>
            </div>

            {{-- Status --}}
            <div class="mb-6">
                <label class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                <select name="status" class="w-full bg-dark-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-primary-500">
                    <option value="pending" {{ old('status', 'pending') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="confirmed" {{ old('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                </select>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                    Record Payment
                </button>
                <a href="{{ route('admin.payments.index') }}" class="text-slate-500 hover:text-slate-300 text-sm transition">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
