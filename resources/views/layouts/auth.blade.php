<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Login') | EduID</title>

    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (CDN fallback for instant loading and preview) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f5f3ff',
                            100: '#ede9fe',
                            200: '#ddd6fe',
                            300: '#c084fc',
                            400: '#a855f7',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            700: '#6d28d9',
                            800: '#5b21b6',
                            900: '#4c1d95',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(-45deg, #0f172a, #1e1b4b, #111827, #020617);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }

        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        /* Glassmorphism utility classes */
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .glass-input {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-input:focus {
            background: rgba(255, 255, 255, 0.05);
            border-color: #8b5cf6;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.25);
            outline: none;
        }
    </style>
    @yield('styles')
</head>
<body class="h-full flex flex-col justify-between text-slate-100 antialiased overflow-hidden relative">

    <!-- Decorative background glow blobs -->
    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-600/15 rounded-full blur-[100px] pointer-events-none -translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-600/10 rounded-full blur-[100px] pointer-events-none translate-x-1/2 translate-y-1/2"></div>

    <!-- Main Content -->
    <div class="flex-grow flex items-center justify-center p-4 sm:p-6 z-10">
        <div class="w-full max-w-md">
            @yield('content')
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-6 text-center text-xs text-slate-500 z-10">
        &copy; {{ date('Y') }} EduID SaaS. All rights reserved. &middot; Powered by Arkeon
    </footer>

    @yield('scripts')
</body>
</html>
