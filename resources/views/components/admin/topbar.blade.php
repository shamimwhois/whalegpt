{{--
    The admin top bar: the mobile menu trigger, the current page's heading, and
    a theme toggle that writes the same localStorage key the rest of the app
    reads.
--}}
<header class="sticky top-0 z-20 border-b border-line bg-page/85 backdrop-blur">
    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
        <div class="lg:hidden">
        <button
            type="button"
            x-on:click="sidebarOpen = true"
            class="whale-icon-button h-9 w-9"
            aria-label="Open navigation"
        >
            <x-ui.icon name="menu" class="h-5 w-5" />
        </button>
        </div>

        <div class="min-w-0">
            <p class="truncate text-sm font-semibold tracking-[-0.01em]">@yield('heading', 'Dashboard')</p>
            <p class="truncate text-[11px] text-muted">@yield('subheading', 'Whale AI administration')</p>
        </div>

        <div class="flex-1"></div>

        <a
            href="{{ route('home') }}"
            class="hidden items-center gap-1.5 rounded-lg px-3 py-2 text-sm text-body transition hover:bg-wash hover:text-ink focus-visible:outline-2 focus-visible:outline-accent sm:inline-flex"
        >
            View site
            <x-ui.icon name="external" class="h-3.5 w-3.5" />
        </a>

        <button
            type="button"
            x-data="{ dark: document.documentElement.classList.contains('dark') }"
            x-on:click="dark = ! dark; document.documentElement.classList.toggle('dark', dark); try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}"
            class="whale-icon-button h-9 w-9"
            :aria-label="dark ? 'Switch to light theme' : 'Switch to dark theme'"
        >
            <span x-show="! dark"><x-ui.icon name="moon" class="h-4 w-4" /></span>
            <span x-show="dark" style="display:none"><x-ui.icon name="sun" class="h-4 w-4" /></span>
        </button>
    </div>
</header>
