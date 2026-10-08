<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Chat') · {{ config('app.name') }}</title>

    <script>
        // Theme must be applied before first paint to avoid a flash.
        if (localStorage.theme === 'dark' ||
            (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }

        // A stable, client-held workspace id. No session or login is required;
        // the id is what scopes a browser's files, projects and chat history.
        window.whaleWorkspaceId = function () {
            let id = null;

            try {
                id = localStorage.getItem('whale.workspace');
            } catch (error) {
                id = null;
            }

            if (!id || !/^[A-Za-z0-9_-]{8,64}$/.test(id)) {
                id = 'ws-' + Array.from(crypto.getRandomValues(new Uint8Array(16)))
                    .map((byte) => byte.toString(16).padStart(2, '0'))
                    .join('');

                try {
                    localStorage.setItem('whale.workspace', id);
                } catch (error) {
                    // Storage disabled: the id lives for this page view only.
                }
            }

            return id;
        };
    </script>

    <meta name="color-scheme" content="light dark">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>

    @stack('head')
</head>
<body class="h-full bg-white font-sans text-[#0d0d0d] antialiased
             dark:bg-[#212121] dark:text-[#ececec]">
    @yield('content')

    @stack('scripts')
</body>
</html>