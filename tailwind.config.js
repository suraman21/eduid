import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.ts',
        './resources/**/*.tsx',
        './resources/**/*.vue',
    ],
    // Classes built by Blade string interpolation (e.g. peer-checked:bg-{$color}-50)
    // aren't visible to the JIT scanner, so they must be safelisted or they get
    // purged from the compiled stylesheet.
    safelist: [
        // Attendance status radios ($color ∈ green|amber|blue|red), applied
        // through the peer-checked variant.
        { pattern: /^(bg|text|border)-(green|amber|blue|red)-(50|300|600)$/, variants: ['peer-checked'] },
        // Photo-session status banner ($status ⇒ blue|gray)
        { pattern: /^(bg|text|border)-(blue|gray)-(50|200|600|800)$/ },
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            // Fixed brand palette used by the admin console and marketing page.
            // (Per-school accent colours are applied via inline styles, not
            // utility classes, so they don't need to be compiled here.)
            colors: {
                // Landing page brand palette (DEFAULT = bare class e.g. bg-primary)
                primary: {
                    DEFAULT: '#091426',
                    50: '#f5f3ff', 100: '#ede9fe', 200: '#ddd6fe', 300: '#c084fc',
                    400: '#a855f7', 500: '#8b5cf6', 600: '#7c3aed', 700: '#6d28d9',
                    800: '#5b21b6', 900: '#4c1d95',
                },
                secondary: {
                    DEFAULT: '#006c49',
                },
                dark: {
                    50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0',
                    700: '#334155', 800: '#1e293b', 850: '#172033',
                    900: '#0f172a', 950: '#020617',
                },
            },
            fontFamily: {
                display: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                pulseSlow: {
                    '0%, 100%': { opacity: '1' },
                    '50%': { opacity: '0.6' },
                },
                spinSlow: {
                    from: { transform: 'rotate(0deg)' },
                    to: { transform: 'rotate(360deg)' },
                },
            },
            animation: {
                fadeIn: 'fadeIn 0.3s ease-out',
                'pulse-slow': 'pulseSlow 3s ease-in-out infinite',
                'spin-slow': 'spinSlow 3s linear infinite',
            },
        },
    },
    plugins: [],
};
