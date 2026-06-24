@extends('layouts.auth')

@section('title', 'Enter Reset Code')

@section('content')
<div class="glass-card rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden">
    <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-purple-500 to-transparent opacity-80"></div>

    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-purple-600 to-blue-500 shadow-lg shadow-purple-500/30 mb-4">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
        </div>
        <h1 class="text-3xl font-extrabold bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">Enter Reset Code</h1>
        <p class="text-slate-400 text-sm mt-1.5 font-light">Check your email for the 6-digit code, then set a new password</p>
    </div>

    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm">
            <ul class="list-disc list-inside font-light">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf

        <div>
            <label for="school_code" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">School Code</label>
            <input type="text" name="school_code" id="school_code" required
                   class="glass-input w-full px-4 py-3 rounded-xl text-sm placeholder-slate-600 focus:ring-2"
                   value="{{ old('school_code', $school_code) }}" autocomplete="off">
        </div>

        <div>
            <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email Address</label>
            <input type="email" name="email" id="email" required
                   class="glass-input w-full px-4 py-3 rounded-xl text-sm placeholder-slate-600 focus:ring-2"
                   value="{{ old('email', $email) }}" autocomplete="email">
        </div>

        <div>
            <label for="otp" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">6-Digit Reset Code</label>
            <input type="text" name="otp" id="otp" required inputmode="numeric" pattern="[0-9]*" maxlength="6"
                   class="glass-input w-full px-4 py-3 rounded-xl text-center text-lg tracking-[0.5em] placeholder-slate-600 focus:ring-2"
                   placeholder="000000" autocomplete="one-time-code" autofocus>
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">New Password</label>
            <input type="password" name="password" id="password" required
                   class="glass-input w-full px-4 py-3 rounded-xl text-sm placeholder-slate-600 focus:ring-2"
                   placeholder="At least 8 characters" autocomplete="new-password">
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Confirm New Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required
                   class="glass-input w-full px-4 py-3 rounded-xl text-sm placeholder-slate-600 focus:ring-2"
                   placeholder="Re-enter password" autocomplete="new-password">
        </div>

        <button type="submit"
                class="w-full py-3.5 px-4 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white font-medium rounded-xl text-sm shadow-lg shadow-purple-500/20 transform hover:-translate-y-0.5 transition-all duration-200">
            Reset Password
        </button>
    </form>
</div>

<div class="mt-6 text-center">
    <a href="{{ route('password.request') }}" class="text-xs text-slate-500 hover:text-slate-300 hover:underline transition">
        Didn't get a code? Request again
    </a>
</div>
@endsection
