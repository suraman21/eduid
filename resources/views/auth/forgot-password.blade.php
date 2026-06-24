@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
<div class="glass-card rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden">
    <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-purple-500 to-transparent opacity-80"></div>

    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-purple-600 to-blue-500 shadow-lg shadow-purple-500/30 mb-4">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
            </svg>
        </div>
        <h1 class="text-3xl font-extrabold bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">Reset Password</h1>
        <p class="text-slate-400 text-sm mt-1.5 font-light">Enter your school code and email to receive a one-time reset code</p>
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

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="school_code" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">School Code</label>
            <input type="text" name="school_code" id="school_code" required
                   class="glass-input w-full px-4 py-3 rounded-xl text-sm placeholder-slate-600 focus:ring-2"
                   placeholder="e.g. AIA" value="{{ old('school_code') }}" autocomplete="off" autofocus>
        </div>

        <div>
            <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email Address</label>
            <input type="email" name="email" id="email" required
                   class="glass-input w-full px-4 py-3 rounded-xl text-sm placeholder-slate-600 focus:ring-2"
                   placeholder="you@school.com" value="{{ old('email') }}" autocomplete="email">
        </div>

        <button type="submit"
                class="w-full py-3.5 px-4 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white font-medium rounded-xl text-sm shadow-lg shadow-purple-500/20 transform hover:-translate-y-0.5 transition-all duration-200">
            Send Reset Code
        </button>
    </form>
</div>

<div class="mt-6 text-center">
    <a href="{{ route('login') }}" class="text-xs text-slate-500 hover:text-slate-300 hover:underline transition">
        &larr; Back to login
    </a>
</div>
@endsection
