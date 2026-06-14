<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EduID — Smart Student Identity & Attendance System for Ethiopian Schools</title>
    <meta name="description" content="Professional PVC student ID cards with QR attendance tracking. Built for Ethiopian schools. Setup in 48 hours.">

    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#f5f3ff', 100: '#ede9fe', 200: '#ddd6fe', 300: '#c084fc',
                            400: '#a855f7', 500: '#8b5cf6', 600: '#7c3aed', 700: '#6d28d9',
                            800: '#5b21b6', 900: '#4c1d95',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #020617;
            color: #e2e8f0;
        }
        .bg-animate {
            background: linear-gradient(-45deg, #0f172a, #1e1b4b, #111827, #020617);
            background-size: 400% 400%;
            animation: gradient 20s ease infinite;
        }
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .glass {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.06);
        }
        .glass-input {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            transition: all 0.3s ease;
        }
        .glass-input:focus {
            background: rgba(255,255,255,0.07);
            border-color: #8b5cf6;
            box-shadow: 0 0 20px rgba(139,92,246,0.2);
            outline: none;
        }
        .glass-input::placeholder { color: #475569; }
        .price-card:hover { transform: translateY(-4px); border-color: rgba(139,92,246,0.3); }
        .feature-card:hover { border-color: rgba(139,92,246,0.2); background: rgba(255,255,255,0.04); }
        .glow-line { background: linear-gradient(90deg, transparent, #8b5cf6, transparent); }
    </style>
</head>
<body class="antialiased">

<!-- ═══ NAVBAR ═══ -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-slate-950/80 backdrop-blur-xl border-b border-white/5">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
        <a href="/" class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-purple-600 to-blue-500 flex items-center justify-center shadow-lg shadow-purple-500/20">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 009 11.571V11a4 4 0 118 0v.571"></path></svg>
            </div>
            <span class="text-lg font-bold text-white">EduID</span>
        </a>
        <div class="flex items-center gap-3 sm:gap-4">
            <a href="#pricing" class="text-sm text-slate-400 hover:text-white transition hidden sm:inline">Pricing</a>
            <a href="#contact" class="text-sm text-slate-400 hover:text-white transition hidden sm:inline">Contact</a>
            <a href="{{ route('login') }}" class="text-sm px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white border border-white/10 transition">School Login</a>
        </div>
    </div>
</nav>

<!-- ═══ HERO ═══ -->
<section class="bg-animate relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
    <div class="absolute top-1/3 left-1/4 w-96 h-96 bg-purple-600/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-600/8 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 text-center">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-medium mb-8">
            <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
            Built for Ethiopian Schools
        </div>

        <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold leading-tight mb-6">
            <span class="bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">Smart Student Identity</span>
            <br>
            <span class="bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">&amp; Attendance System</span>
        </h1>

        <p class="text-lg sm:text-xl text-slate-400 max-w-2xl mx-auto mb-10 font-light leading-relaxed">
            Professional PVC ID cards with QR-powered attendance tracking.
            One system for registration, photos, cards, and daily attendance.
            Your school runs smarter from day one.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="#contact" class="w-full sm:w-auto px-8 py-3.5 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white font-medium rounded-xl shadow-lg shadow-purple-500/20 hover:shadow-purple-500/30 transform hover:-translate-y-0.5 transition-all text-sm">
                Get Started — Free Demo
            </a>
            <a href="#features" class="w-full sm:w-auto px-8 py-3.5 bg-white/5 hover:bg-white/10 text-slate-300 font-medium rounded-xl border border-white/10 hover:border-white/20 transition-all text-sm">
                See How It Works
            </a>
        </div>
    </div>
</section>

<!-- ═══ FEATURES ═══ -->
<section id="features" class="py-20 sm:py-28">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-16">
            <p class="text-xs font-semibold text-purple-400 uppercase tracking-widest mb-3">Everything You Need</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">One Platform, Complete Control</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @php
            $features = [
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>', 'title' => 'Student Registration', 'desc' => 'CSV bulk import or manual entry. Ethiopian & Gregorian calendar support. Full guardian info.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>', 'title' => 'Photo Session Mode', 'desc' => 'Structured photography workflow. Queue students by class, capture and crop — no chaos.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15A2.25 2.25 0 002.25 6.75v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/>', 'title' => 'Professional PVC Cards', 'desc' => 'We print and deliver real PVC ID cards with QR codes. No printers needed at your school.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>', 'title' => 'QR Attendance Scanning', 'desc' => 'Teachers scan student cards with their phone. Works 100% offline — syncs when connected.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>', 'title' => 'Mobile App (Flutter)', 'desc' => 'Teachers get a dedicated Android app for attendance, student lookup, and class management.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>', 'title' => 'Secure Multi-Tenant', 'desc' => 'Each school\'s data is completely isolated. Role-based access. Full audit logging.'],
            ];
            @endphp

            @foreach($features as $f)
            <div class="glass rounded-2xl p-6 transition-all duration-300 feature-card">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $f['icon'] !!}</svg>
                </div>
                <h3 class="text-base font-semibold text-white mb-2">{{ $f['title'] }}</h3>
                <p class="text-sm text-slate-400 font-light leading-relaxed">{{ $f['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- ═══ HOW IT WORKS ═══ -->
<section class="py-20 sm:py-24 border-t border-white/5">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-16">
            <p class="text-xs font-semibold text-purple-400 uppercase tracking-widest mb-3">Simple Process</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Up and Running in 48 Hours</h2>
        </div>

        <div class="space-y-6">
            @php
            $steps = [
                ['n' => '01', 'title' => 'Sign Up & Import', 'desc' => 'We create your school account. You upload your student list via CSV — names, classes, guardian info. Done in minutes.'],
                ['n' => '02', 'title' => 'Photo Session', 'desc' => 'Use the app\'s Photo Session Mode to photograph students class by class. The system queues them automatically.'],
                ['n' => '03', 'title' => 'Cards Printed & Delivered', 'desc' => 'We print professional PVC ID cards with unique QR codes and deliver them to your school. No equipment needed.'],
                ['n' => '04', 'title' => 'Scan & Track Attendance', 'desc' => 'Teachers open the app, scan student cards — even without internet. Attendance data syncs automatically.'],
            ];
            @endphp

            @foreach($steps as $s)
            <div class="glass rounded-2xl p-6 sm:p-8 flex gap-5 sm:gap-6 items-start">
                <div class="shrink-0 w-11 h-11 rounded-xl bg-gradient-to-tr from-purple-600/20 to-blue-600/20 flex items-center justify-center text-sm font-bold text-purple-400 border border-purple-500/20">{{ $s['n'] }}</div>
                <div>
                    <h3 class="text-base font-semibold text-white mb-1.5">{{ $s['title'] }}</h3>
                    <p class="text-sm text-slate-400 font-light leading-relaxed">{{ $s['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- ═══ PRICING ═══ -->
<section id="pricing" class="py-20 sm:py-28 border-t border-white/5">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-16">
            <p class="text-xs font-semibold text-purple-400 uppercase tracking-widest mb-3">Transparent Pricing</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">One-Time Setup. No Monthly Fees.</h2>
            <p class="text-slate-400 mt-3 text-sm font-light">Setup includes software, training, and your first batch of printed PVC cards.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            {{-- Starter --}}
            <div class="glass rounded-2xl p-7 transition-all duration-300 price-card relative">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest mb-4">Starter</p>
                <div class="flex items-baseline gap-1 mb-1">
                    <span class="text-4xl font-extrabold text-white">12,000</span>
                    <span class="text-sm text-slate-500">ETB</span>
                </div>
                <p class="text-xs text-slate-500 mb-6">One-time setup fee</p>
                <ul class="space-y-3 text-sm text-slate-400 mb-8">
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> Up to 300 students</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> 300 PVC cards printed & delivered</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> QR attendance tracking</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> 1 admin + 10 teachers</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> Mobile app access</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> 6 months maintenance</li>
                </ul>
                <a href="#contact" class="block w-full text-center py-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 text-sm font-medium border border-white/10 transition">Get Started</a>
            </div>

            {{-- Standard (Recommended) --}}
            <div class="glass rounded-2xl p-7 transition-all duration-300 price-card relative border-purple-500/30">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 bg-gradient-to-r from-purple-600 to-blue-600 rounded-full text-xs font-semibold text-white shadow-lg shadow-purple-500/20">Most Popular</div>
                <p class="text-xs font-semibold text-purple-400 uppercase tracking-widest mb-4">Standard</p>
                <div class="flex items-baseline gap-1 mb-1">
                    <span class="text-4xl font-extrabold text-white">22,000</span>
                    <span class="text-sm text-slate-500">ETB</span>
                </div>
                <p class="text-xs text-slate-500 mb-6">One-time setup fee</p>
                <ul class="space-y-3 text-sm text-slate-400 mb-8">
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> Up to 800 students</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> 800 PVC cards printed & delivered</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> CSV bulk import + Excel export</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> Photo Session Mode</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> 1 admin + 30 teachers</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> 1 year maintenance + training</li>
                </ul>
                <a href="#contact" class="block w-full text-center py-3 rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white text-sm font-medium shadow-lg shadow-purple-500/20 transition">Get Started</a>
            </div>

            {{-- Premium --}}
            <div class="glass rounded-2xl p-7 transition-all duration-300 price-card relative">
                <p class="text-xs font-semibold text-amber-400 uppercase tracking-widest mb-4">Premium</p>
                <div class="flex items-baseline gap-1 mb-1">
                    <span class="text-4xl font-extrabold text-white">35,000</span>
                    <span class="text-sm text-slate-500">ETB</span>
                </div>
                <p class="text-xs text-slate-500 mb-6">One-time setup fee</p>
                <ul class="space-y-3 text-sm text-slate-400 mb-8">
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> Unlimited students</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> Unlimited PVC cards first batch</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> Custom card design</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> 2-day on-site training</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> Priority support</li>
                    <li class="flex items-center gap-2"><span class="text-green-400">✓</span> 2 year maintenance</li>
                </ul>
                <a href="#contact" class="block w-full text-center py-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 text-sm font-medium border border-white/10 transition">Get Started</a>
            </div>
        </div>

        <p class="text-center text-xs text-slate-500 mt-8 font-light">Annual card renewals: 50–80 ETB per card. Cards expire yearly to keep attendance secure.</p>
    </div>
</section>

<!-- ═══ CONTACT / LEAD FORM ═══ -->
<section id="contact" class="py-20 sm:py-28 border-t border-white/5 bg-animate relative">
    <div class="absolute top-1/3 right-1/4 w-80 h-80 bg-purple-600/8 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="relative max-w-xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-10">
            <p class="text-xs font-semibold text-purple-400 uppercase tracking-widest mb-3">Get In Touch</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Ready to Modernize Your School?</h2>
            <p class="text-slate-400 mt-3 text-sm font-light">Fill in your details and we'll contact you within 24 hours with a free demo.</p>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-300 text-sm flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            {{ session('error') }}
        </div>
        @endif

        @if($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm">
            <ul class="list-disc list-inside space-y-1 font-light">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="glass rounded-3xl p-8 sm:p-10 shadow-2xl">
            <div class="absolute top-0 left-0 right-0 h-[2px] glow-line opacity-60 rounded-t-3xl"></div>

            <form method="POST" action="{{ route('contact.store') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Your Name *</label>
                        <input type="text" name="name" required value="{{ old('name') }}" placeholder="Abebe Kebede" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">School Name *</label>
                        <input type="text" name="school_name" required value="{{ old('school_name') }}" placeholder="Bole Preparatory School" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Phone *</label>
                        <input type="tel" name="phone" required value="{{ old('phone') }}" placeholder="+251 9XX XXX XXXX" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="you@school.com" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Interested Plan</label>
                    <select name="preferred_plan" class="glass-input w-full px-4 py-3 rounded-xl text-sm appearance-none">
                        <option value="" class="bg-slate-900">Not sure yet</option>
                        <option value="starter" {{ old('preferred_plan') === 'starter' ? 'selected' : '' }} class="bg-slate-900">Starter — 12,000 ETB</option>
                        <option value="standard" {{ old('preferred_plan') === 'standard' ? 'selected' : '' }} class="bg-slate-900">Standard — 22,000 ETB</option>
                        <option value="premium" {{ old('preferred_plan') === 'premium' ? 'selected' : '' }} class="bg-slate-900">Premium — 35,000 ETB</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Message</label>
                    <textarea name="message" rows="3" placeholder="Tell us about your school — how many students, any specific needs..." class="glass-input w-full px-4 py-3 rounded-xl text-sm resize-none">{{ old('message') }}</textarea>
                </div>

                <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white font-medium rounded-xl text-sm shadow-lg shadow-purple-500/20 hover:shadow-purple-500/30 transform hover:-translate-y-0.5 transition-all">
                    Request Free Demo
                </button>
            </form>
        </div>
    </div>
</section>

<!-- ═══ FOOTER ═══ -->
<footer class="py-10 border-t border-white/5">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-purple-600 to-blue-500 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 009 11.571V11a4 4 0 118 0v.571"></path></svg>
                </div>
                <span class="text-sm font-semibold text-slate-400">EduID SaaS</span>
            </div>
            <div class="flex items-center gap-6 text-xs text-slate-500">
                <a href="{{ route('login') }}" class="hover:text-slate-300 transition">School Login</a>
                <a href="{{ route('admin.login') }}" class="hover:text-slate-300 transition">Admin</a>
                <span>&copy; {{ date('Y') }} Arkeon Ethiopia</span>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
