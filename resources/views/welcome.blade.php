<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Digibase') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
        .pulse-dot { animation: pulse-dot 2s ease-in-out infinite; }
    </style>
</head>
<body class="bg-[#0a0a0a] text-white min-h-screen flex flex-col items-center justify-center px-6">

    {{-- Main Card --}}
    <div class="w-full max-w-lg text-center space-y-8">

        {{-- Brand --}}
        <div>
            <h1 class="text-4xl font-bold tracking-tight">
                {{ config('app.name', 'Digibase') }}
            </h1>
            <p class="mt-2 text-gray-400 text-sm">
                Self-hosted Backend as a Service
            </p>
        </div>

        {{-- Status Card --}}
        <div class="bg-[#111111] border border-[#1e1e1e] rounded-xl p-6 space-y-4">
            <div class="flex items-center justify-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span class="pulse-dot absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                <span class="text-emerald-400 font-medium text-sm">All Systems Operational</span>
            </div>

            <div class="grid grid-cols-3 gap-4 text-center pt-2">
                <div>
                    <div class="text-2xl font-bold text-white">API</div>
                    <div class="text-xs text-gray-500 mt-1">Online</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-white">DB</div>
                    <div class="text-xs text-gray-500 mt-1">Connected</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-white">Auth</div>
                    <div class="text-xs text-gray-500 mt-1">Sanctum</div>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="/admin"
               class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white text-black font-semibold rounded-lg hover:bg-gray-200 transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                </svg>
                Login to Console
            </a>
            <a href="/docs/api"
               class="inline-flex items-center justify-center gap-2 px-6 py-3 border border-[#333] text-gray-300 font-semibold rounded-lg hover:border-gray-500 hover:text-white transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
                API Documentation
            </a>
        </div>
    </div>

    {{-- Footer --}}
    <div class="absolute bottom-6 text-center text-xs text-gray-600">
        {{ config('app.name', 'Digibase') }} &mdash; Powered by Laravel &amp; FilamentPHP
    </div>

</body>
</html>
