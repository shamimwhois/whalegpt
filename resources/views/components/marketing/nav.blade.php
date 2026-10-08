{{--
    The public header.

    Shared by the landing page and the docs. The sign-in controls only appear
    for a guest; a signed-in visitor gets a straight link into the app instead,
    which is what keeps the marketing page useful for both audiences.
--}}
@php
    $links = [
        ['label' => 'Features', 'href' => route('home').'#features'],
        ['label' => 'How it works', 'href' => route('home').'#how'],
        ['label' => 'Docs', 'href' => route('docs.index')],
    ];
@endphp

<header class="sticky top-0 z-40 border-b border-line bg-page/85 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6 lg:px-8">

        <a href="{{ route('home') }}" class="shrink-0 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" aria-label="{{ config('app.name') }}, home">
            <x-ui.logo />
        </a>

        <nav class="ml-4 hidden items-center gap-1 md:flex" aria-label="Primary">
            @foreach ($links as $link)
                <a
                    href="{{ $link['href'] }}"
                    class="rounded-lg px-3 py-2 text-sm text-body transition hover:bg-wash hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
                >{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex-1"></div>

        <button
            type="button"
            x-on:click="toggleTheme()"
            class="whale-icon-button h-9 w-9"
            :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
        >
            <span x-show="! isDark"><x-ui.icon name="moon" class="h-4 w-4" /></span>
            <span x-show="isDark" style="display:none"><x-ui.icon name="sun" class="h-4 w-4" /></span>
        </button>

        @auth
            <a
                href="{{ route('chat.index') }}"
                class="hidden items-center gap-1.5 rounded-xl bg-accent px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent sm:inline-flex"
            >
                Open app
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
        @else
            <button
                type="button"
                x-on:click="authTab = 'login'; authOpen = true"
                class="hidden rounded-xl px-3.5 py-2 text-sm font-medium text-body transition hover:bg-wash hover:text-ink focus-visible:outline-2 focus-visible:outline-accent sm:block"
            >Sign in</button>

            <button
                type="button"
                x-on:click="authTab = 'register'; authOpen = true"
                class="hidden rounded-xl bg-accent px-3.5 py-2 text-sm font-medium text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent sm:block"
            >Get started</button>
        @endauth

        {{-- The responsive hide lives on the wrapper: `.whale-icon-button`
             sets display on the button itself, and an unlayered rule beats a
             layered `md:hidden` utility. --}}
        <div class="md:hidden">
        <button
            type="button"
            x-on:click="mobileOpen = ! mobileOpen"
            class="whale-icon-button h-9 w-9"
            :aria-expanded="mobileOpen ? 'true' : 'false'"
            aria-label="Open menu"
        >
            <x-ui.icon name="menu" class="h-5 w-5" x-show="! mobileOpen" />
            <x-ui.icon name="close" class="h-5 w-5" x-show="mobileOpen" style="display:none" />
        </button>
        </div>
    </div>

    {{-- The mobile drawer. Links and the auth actions both live here, because
         the header collapses them below md. --}}
    <div
        x-show="mobileOpen"
        x-transition.origin.top
        style="display:none"
        class="border-t border-line bg-page md:hidden"
    >
        <nav class="mx-auto max-w-6xl px-4 py-3 sm:px-6" aria-label="Mobile">
            @foreach ($links as $link)
                <a
                    href="{{ $link['href'] }}"
                    x-on:click="mobileOpen = false"
                    class="block rounded-lg px-3 py-2.5 text-sm text-body transition hover:bg-wash hover:text-ink"
                >{{ $link['label'] }}</a>
            @endforeach

            <div class="my-2 border-t border-line"></div>

            @auth
                <a href="{{ route('chat.index') }}" class="block rounded-lg bg-accent px-3 py-2.5 text-center text-sm font-medium text-white">Open app</a>
            @else
                <div class="flex flex-col gap-2 pb-1">
                    <button type="button" x-on:click="authTab = 'login'; authOpen = true; mobileOpen = false"
                            class="rounded-xl border border-line px-3 py-2.5 text-sm font-medium text-ink">Sign in</button>
                    <button type="button" x-on:click="authTab = 'register'; authOpen = true; mobileOpen = false"
                            class="rounded-xl bg-accent px-3 py-2.5 text-sm font-medium text-white">Get started</button>
                </div>
            @endauth
        </nav>
    </div>
</header>
