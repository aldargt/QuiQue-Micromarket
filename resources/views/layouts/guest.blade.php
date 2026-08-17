<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <script>try{if(localStorage.getItem('quique-theme')==='dark'){document.documentElement.classList.add('dark');document.documentElement.style.colorScheme='dark'}}catch(e){}</script>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'QuiQue Micromarket') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/quique-favicon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased dark:text-slate-100">
        <div class="relative flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-[#dff1f4] via-white to-[#ccecf1] px-4 py-8 dark:from-[#11161c] dark:via-[#161b22] dark:to-[#1b3035]">
            <div class="absolute right-4 top-4 sm:right-6 sm:top-6">
                <x-theme-toggle :show-label="true" />
            </div>
            <div>
                <a href="{{ route('login') }}">
                    <x-application-logo class="h-28 w-28 rounded-2xl bg-white shadow-lg ring-4 ring-white" />
                </a>
            </div>

            <div class="mt-6 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-xl shadow-slate-200/60 dark:border-slate-700 dark:bg-[#252b34] dark:shadow-black/30 sm:max-w-md sm:px-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
