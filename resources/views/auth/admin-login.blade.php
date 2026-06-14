@extends('layouts.auth')

@section('title', 'Super Admin Login')

@section('content')
<div class="glass-card rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden transition-all duration-500 hover:shadow-indigo-500/5 hover:border-white/10">
    <!-- Gold/Indigo Glow lines on card top -->
    <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-amber-500 to-transparent opacity-80"></div>

    <div class="mb-8 text-center">
        <!-- Logo Icon Container -->
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-600 to-indigo-600 shadow-lg shadow-amber-500/30 mb-4 transform hover:rotate-6 transition-transform duration-300">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
        </div>
        <h1 class="text-3xl font-extrabold bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">Arkeon Panel</h1>
        <p class="text-slate-400 text-sm mt-1.5 font-light">Super Admin Control Center</p>
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

    <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
        @csrf

        <!-- Email Input -->
        <div>
            <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Admin Email</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                    </svg>
                </span>
                <input type="email" name="email" id="email" required 
                       class="glass-input w-full pl-11 pr-4 py-3 rounded-xl text-sm placeholder-slate-600 focus:ring-2"
                       placeholder="admin@arkeon.com" value="{{ old('email') }}" autocomplete="email" autofocus>
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

        <!-- Remember Me Checkbox -->
        <div class="flex items-center justify-between text-xs text-slate-400 font-light pt-1">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-700 bg-slate-800/50 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900">
                <span>Remember me</span>
            </label>
        </div>

        <!-- Submit Button -->
        <button type="submit" 
                class="w-full py-3.5 px-4 bg-gradient-to-r from-amber-600 to-indigo-600 hover:from-amber-500 hover:to-indigo-500 text-white font-medium rounded-xl text-sm shadow-lg shadow-amber-500/10 hover:shadow-amber-500/20 transform hover:-translate-y-0.5 transition-all duration-200">
            Sign In to Panel
        </button>
    </form>
</div>

<!-- School Portal Redirection -->
<div class="mt-6 text-center">
    <a href="{{ route('login') }}" class="text-xs text-slate-500 hover:text-slate-300 hover:underline transition">
        Go back to School Portal login &rarr;
    </a>
</div>
@endsection
