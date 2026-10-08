<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Whale AI — a local-first AI assistant')</title>
    <meta name="description" content="@yield('description', 'Whale AI helps you chat, code, research and create, running on the models you already have.')">
    <meta name="color-scheme" content="light dark">

    <script>
        // Same contract as the chat layout: the class on <html> is the one
        // source of truth, so the in-app toggle and this page agree.
        if (localStorage.theme === 'dark' ||
            (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }

        // One scope shared by the navigation, the mobile drawer and the auth
        // modal, so opening the modal from the header works from any page.
        function marketingShell() {
            return {
                authOpen: @json($errors->any() || session('auth_error') !== null),
                authTab: @json(old('name') !== null || ($errors->has('password') && ! $errors->has('email')) ? 'register' : 'login'),
                mobileOpen: false,
                isDark: document.documentElement.classList.contains('dark'),

                toggleTheme() {
                    this.isDark = ! this.isDark;
                    document.documentElement.classList.toggle('dark', this.isDark);

                    try {
                        localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
                    } catch (error) {
                        // Storage disabled: the choice lives for this page only.
                    }
                },
            };
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>

    <style>
        /* The hero's life: a slow bob and a stagger-in. Both stand down under
           prefers-reduced-motion so the page stays still for anyone who asked. */
        @keyframes whale-rise {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: none; }
        }

        @keyframes whale-bob {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-14px); }
        }

        .whale-rise { animation: whale-rise .7s cubic-bezier(.2,.7,.3,1) both; }

        @media (prefers-reduced-motion: reduce) {
            .whale-rise { animation: none; }
        }
    </style>

    @stack('head')
</head>
<body class="min-h-full bg-page font-sans text-ink antialiased">
    <div x-data="marketingShell()" x-on:keydown.escape.window="authOpen = false">
        <x-marketing.nav />

        <main>
            @yield('content')
        </main>

        <x-marketing.footer />

        <x-marketing.auth-modal />
    </div>

    @stack('scripts')
</body>
</html>
