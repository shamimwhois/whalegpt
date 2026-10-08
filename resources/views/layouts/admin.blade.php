{{--
    The admin shell.

    A fixed sidebar on desktop and an off-canvas drawer below it, with the panel
    content in the remaining column. It is deliberately its own layout rather
    than an extension of the marketing one: the admin is an operator surface,
    not part of the public site, and it should not carry a sign-in modal.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · {{ config('app.name') }}</title>
    <meta name="color-scheme" content="light dark">

    <script>
        if (localStorage.theme === 'dark' ||
            (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>

    @stack('head')
</head>
<body class="min-h-full bg-page font-sans text-ink antialiased">
    <div x-data="{ sidebarOpen: false }" class="min-h-dvh lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]">
        <x-admin.sidebar />

        <div class="flex min-w-0 flex-col">
            <x-admin.topbar />

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-6xl">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
