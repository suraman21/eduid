@extends('layouts.auth')

@section('title', 'School Login')

@section('content')
<div class="glass-card rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden transition-all duration-500 hover:shadow-purple-500/5 hover:border-white/10">
    <!-- Glow lines on card top -->
    <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-purple-500 to-transparent opacity-80"></div>

    <div class="mb-8 text-center">
        <!-- Logo Icon Container -->
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-purple-600 to-blue-500 shadow-lg shadow-purple-500/30 mb-4 transform hover:rotate-6 transition-transform duration-300">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 009 11.571V11a4 4 0 118 0v.571c0 2.219.827 4.21 2.197 5.73A2 2 0 0118.528 20.5a13.945 13.945 0 01-6.528-1.5m0-13a8.97 8.97 0 011.83 5.371m0-5.371C12 5.16 11.737 5 11.5 5a3.5 3.5 0 100 7c.237 0 .5-.16.5-.371m0-5.371v5.371"></path>
            </svg>
        </div>
        <h1 class="text-3xl font-extrabold bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">EduID Portal</h1>
        <p class="text-slate-400 text-sm mt-1.5 font-light">Access your smart school identity system</p>
    </div>

    <!-- Error Alerts -->
    @if ($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 text-rose-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <span class="font-semibold">Authentication failed</span>
                <ul class="list-disc list-inside mt-1 font-light text-rose-300/95">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- School Code Input -->
        <div>
            <label for="school_code" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">School Code</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </span>
                <input type="text" name="school_code" id="school_code" required 
                       class="glass-input w-full pl-11 pr-4 py-3 rounded-xl text-sm placeholder-slate-600 focus:ring-2"
                       placeholder="e.g. AIA" value="{{ old('school_code') }}" autocomplete="off" autofocus>
            </div>
        </div>

        <!-- Email Input -->
        <div>
            <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email Address</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                    </svg>
                </span>
                <input type="email" name="email" id="email" required 
                       class="glass-input w-full pl-11 pr-4 py-3 rounded-xl text-sm placeholder-slate-600 focus:ring-2"
                       placeholder="you@school.com" value="{{ old('email') }}" autocomplete="email">
            </div>
        </div>

        <!-- Password Input -->
        <div>
            <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Password</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </span>
                <input type="password" name="password" id="password" required 
                       class="glass-input w-full pl-11 pr-4 py-3 rounded-xl text-sm placeholder-slate-600 focus:ring-2"
                       placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" autocomplete="current-password">
            </div>
        </div>

        <!-- Remember Me and Forgot Password -->
        <div class="flex items-center justify-between text-xs text-slate-400 font-light pt-1">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-700 bg-slate-800/50 text-purple-600 focus:ring-purple-500 focus:ring-offset-slate-900">
                <span>Remember me</span>
            </label>
            <a href="#" class="hover:text-purple-400 transition">Forgot password?</a>
        </div>

        <!-- Submit Button -->
        <button type="submit" 
                class="w-full py-3.5 px-4 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white font-medium rounded-xl text-sm shadow-lg shadow-purple-500/20 hover:shadow-purple-500/30 transform hover:-translate-y-0.5 transition-all duration-200">
            Sign In to Portal
        </button>
    </form>
</div>

<!-- Super Admin Redirection -->
<div class="mt-6 text-center">
    <a href="{{ route('admin.login') }}" class="text-xs text-slate-500 hover:text-slate-300 hover:underline transition">
        Looking for Super Admin login? Click here &rarr;
    </a>
</div>
@endsection
