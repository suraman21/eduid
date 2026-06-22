<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | {{ $currentSchool->name ?? 'EduID' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        accent: {
                            50: '#f0f5ff', 100: '#e0eaff', 200: '#c1d5ff', 300: '#92b5ff',
                            400: '#5a8eff', 500: '{{ $currentSchool->accent_color ?? "#6366f1" }}',
                            600: '#4338ca', 700: '#3730a3', 800: '#312e81', 900: '#1e1b4b',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .sidebar-link { transition: all 0.15s ease; }
        .sidebar-link:hover, .sidebar-link.active { background: rgba(99, 102, 241, 0.08); color: {{ $currentSchool->accent_color ?? '#6366f1' }}; }
        .sidebar-link.active { border-right: 3px solid {{ $currentSchool->accent_color ?? '#6366f1' }}; }
    </style>
    @yield('styles')
</head>
<body class="h-full bg-gray-50 text-gray-800 antialiased">
    <div class="flex h-full">

        {{-- Sidebar --}}
        <aside class="w-64 bg-white border-r border-gray-200 flex flex-col flex-shrink-0 fixed inset-y-0 left-0 z-30" id="sidebar">
            {{-- Logo --}}
            <div class="h-16 flex items-center px-5 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    @if($currentSchool->logo_url ?? false)
                        <img src="{{ $currentSchool->logo_url }}" class="w-9 h-9 rounded-lg object-cover" alt="">
                    @else
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center text-white text-sm font-bold" style="background: {{ $currentSchool->accent_color ?? '#6366f1' }}">
                            {{ strtoupper(substr($currentSchool->name ?? 'S', 0, 2)) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 truncate">{{ $currentSchool->name ?? 'School' }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ $currentSchool->code ?? '' }}</p>
                    </div>
                </div>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 py-4 overflow-y-auto">
                <div class="px-4 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Main</div>
                <a href="{{ route('school.dashboard') }}" class="sidebar-link flex items-center gap-3 px-6 py-2.5 text-sm {{ request()->routeIs('school.dashboard') ? 'active font-medium' : 'text-gray-500' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1m-2 0h2"/></svg>
                    Dashboard
                </a>

                <div class="px-4 mt-5 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">People</div>
                <a href="{{ route('school.students.index') }}" class="sidebar-link flex items-center gap-3 px-6 py-2.5 text-sm {{ request()->routeIs('school.students.*') ? 'active font-medium' : 'text-gray-500' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    Students
                </a>
                <a href="{{ route('school.staff.index') }}" class="sidebar-link flex items-center gap-3 px-6 py-2.5 text-sm {{ request()->routeIs('school.staff.*') ? 'active font-medium' : 'text-gray-500' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Staff
                </a>

                <div class="px-4 mt-5 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Academics</div>
                <a href="{{ route('school.classes.index') }}" class="sidebar-link flex items-center gap-3 px-6 py-2.5 text-sm {{ request()->routeIs('school.classes.*') ? 'active font-medium' : 'text-gray-500' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Classes
                </a>
                <a href="{{ route('school.subjects.index') }}" class="sidebar-link flex items-center gap-3 px-6 py-2.5 text-sm {{ request()->routeIs('school.subjects.*') ? 'active font-medium' : 'text-gray-500' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Subjects
                </a>
                <a href="{{ route('school.attendance.index') }}" class="sidebar-link flex items-center gap-3 px-6 py-2.5 text-sm {{ request()->routeIs('school.attendance.*') ? 'active font-medium' : 'text-gray-500' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    Attendance
                </a>
                <a href="{{ route('school.grades.index') }}" class="sidebar-link flex items-center gap-3 px-6 py-2.5 text-sm {{ request()->routeIs('school.grades.*') ? 'active font-medium' : 'text-gray-500' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Grades
                </a>

                <div class="px-4 mt-5 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Services</div>
                <a href="{{ route('school.photo-sessions.index') }}" class="sidebar-link flex items-center gap-3 px-6 py-2.5 text-sm {{ request()->routeIs('school.photo-sessions.*') ? 'active font-medium' : 'text-gray-500' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Photo Sessions
                </a>
                <a href="{{ route('school.card-orders.index') }}" class="sidebar-link flex items-center gap-3 px-6 py-2.5 text-sm {{ request()->routeIs('school.card-orders.*') ? 'active font-medium' : 'text-gray-500' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1"/></svg>
                    ID Cards
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-2.5 text-sm text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Report Cards
                </a>
            </nav>

            {{-- User --}}
            <div class="border-t border-gray-100 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background: {{ $currentSchool->accent_color ?? '#6366f1' }}">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-400 truncate capitalize">{{ str_replace('_', ' ', auth()->user()->role) }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-400 hover:text-red-500 transition" title="Logout">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Main --}}
        <div class="flex-1 flex flex-col ml-64">
            <header class="h-16 bg-white/80 backdrop-blur border-b border-gray-100 flex items-center justify-between px-6 sticky top-0 z-20">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">@yield('heading', 'Dashboard')</h1>
                    @hasSection('subheading')
                        <p class="text-xs text-gray-400">@yield('subheading')</p>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    @if($currentSchool->subscription_status !== 'active')
                        <span class="text-xs px-2.5 py-1 rounded-full {{ $currentSchool->subscription_status === 'grace' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600' }}">
                            {{ ucfirst($currentSchool->subscription_status) }}
                        </span>
                    @endif
                    <span class="text-xs text-gray-400">{{ now()->format('M d, Y') }}</span>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                @if(session('success'))
                    <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">{{ session('error') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    @yield('scripts')
</body>
</html>
