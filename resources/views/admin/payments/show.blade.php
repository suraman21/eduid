@extends('layouts.admin')

@section('title', 'Payment #'.$payment->id)
@section('heading', 'Payment #'.$payment->id)
@section('subheading', $payment->school->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.payments.index') }}" class="text-slate-500 hover:text-primary-400 text-sm transition flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Payments
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Detail Card --}}
    <div class="lg:col-span-2">
        <div class="bg-dark-900 rounded-xl border border-slate-800 p-6">
            <h3 class="text-sm font-semibold text-white mb-5">Payment Details</h3>
            <dl class="divide-y divide-slate-800/50">
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">School</dt>
                    <dd class="col-span-2 text-sm text-white">{{ $payment->school->name ?? '—' }}</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Plan</dt>
                    <dd class="col-span-2 text-sm text-white">{{ $payment->plan->name ?? '—' }}</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Amount (ETB)</dt>
                    <dd class="col-span-2 text-sm text-white font-mono">{{ number_format($payment->amount_etb, 2) }}</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Method</dt>
                    <dd class="col-span-2 text-sm text-white">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</dd>
                </div>
                @if($payment->bank_name)
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Bank</dt>
                    <dd class="col-span-2 text-sm text-white">{{ $payment->bank_name }}</dd>
                </div>
                @endif
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Reference</dt>
                    <dd class="col-span-2 text-sm text-white font-mono">{{ $payment->reference_number ?? '—' }}</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Payment Date</dt>
                    <dd class="col-span-2 text-sm text-slate-400">{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : '—' }}</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Period</dt>
                    <dd class="col-span-2 text-sm text-slate-400">{{ $payment->period_months ?? 1 }} month(s)</dd>
                </div>
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Status</dt>
                    <dd class="col-span-2">
                        @if($payment->status === 'confirmed')
                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-500/10 text-green-400">Confirmed</span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-500/10 text-yellow-400">Pending</span>
                        @endif
                    </dd>
                </div>
                @if($payment->notes)
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Notes</dt>
                    <dd class="col-span-2 text-sm text-slate-300 whitespace-pre-wrap">{{ $payment->notes }}</dd>
                </div>
                @endif
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Recorded By</dt>
                    <dd class="col-span-2 text-sm text-slate-400">{{ $payment->recordedBy->name ?? '—' }}</dd>
                </div>
                @if($payment->confirmedBy)
                <div class="py-3 grid grid-cols-3 gap-4">
                    <dt class="text-xs font-medium text-slate-500 uppercase">Confirmed By</dt>
                    <dd class="col-span-2 text-sm text-slate-400">{{ $payment->confirmedBy->name }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    {{-- Actions Card --}}
    <div>
        <div class="bg-dark-900 rounded-xl border border-slate-800 p-6">
            <h3 class="text-sm font-semibold text-white mb-5">Actions</h3>

            @if($payment->status === 'pending')
                <form method="POST" action="{{ route('admin.payments.confirm', $payment) }}" class="mb-4">
                    @csrf
                    <button type="submit"
                            class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                        Confirm Payment
                    </button>
                </form>
            @else
                <div class="mb-4 text-center">
                    <span class="text-xs px-3 py-1 rounded-full bg-green-500/10 text-green-400">Payment Confirmed</span>
                </div>
            @endif

            <a href="{{ route('admin.payments.index') }}" class="block text-center text-sm text-slate-500 hover:text-slate-300 transition">
                Back to Payments
            </a>
        </div>
    </div>
</div>
@endsection
