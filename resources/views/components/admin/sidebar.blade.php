{{--
    The admin navigation.

    A fixed rail on large screens and an off-canvas drawer below lg, driven by
    the `sidebarOpen` flag on the layout's root scope.
--}}
@php
    $nav = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'grid'],
        ['label' => 'Chat app', 'route' => 'chat.index', 'icon' => 'sparkles'],
        ['label' => 'Documentation', 'route' => 'docs.index', 'icon' => 'folder'],
        ['label' => 'Landing page', 'route' => 'home', 'icon' => 'globe'],
    ];
@endphp

{{-- Off-canvas scrim. --}}
{{-- Visibility is driven by classes rather than x-show: an inline
     display:none makes Alpine restore that value on show, which leaves the
     scrim permanently hidden. `lg:hidden` removes it on desktop. --}}
<div
    x-bind:class="sidebarOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'"
    x-on:click="sidebarOpen = false"
    class="fixed inset-0 z-30 bg-black/40 opacity-0 transition-opacity duration-200 lg:hidden"
    aria-hidden="true"
></div>

<aside
    x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-line bg-sidebar transition-transform lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0"
    aria-label="Admin"
>
    <div class="flex h-16 items-center gap-2 border-b border-line px-4">
        <a href="{{ route('admin.dashboard') }}" class="rounded-lg focus-visible:outline-2 focus-visible:outline-accent">
            <x-ui.logo />
        </a>

        <div class="ml-auto lg:hidden">
        <button
            type="button"
            x-on:click="sidebarOpen = false"
            class="whale-icon-button h-8 w-8"
            aria-label="Close navigation"
        >
            <x-ui.icon name="close" class="h-4 w-4" />
        </button>
        </div>
    </div>

    <div class="px-3 pt-4">
        <span class="inline-flex items-center gap-1.5 rounded-full border border-line px-2.5 py-1 text-[11px] font-medium text-muted">
            <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
            Administrator
        </span>
    </div>

    <nav class="flex-1 space-y-1 px-3 py-4" aria-label="Admin sections">
        @foreach ($nav as $item)
            @php $active = request()->routeIs($item['route']); @endphp

            <a
                href="{{ route($item['route']) }}"
                @if ($active) aria-current="page" @endif
                class="whale-row whale-row-hover px-3 py-2.5"
            >
                <x-ui.icon :name="$item['icon']" class="h-4 w-4 shrink-0 opacity-80" />
                <span class="truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="border-t border-line p-3">
        <p class="px-2 text-[11px] text-faint">
            Signed in as
            <span class="block truncate font-medium text-body">{{ auth()->user()?->email }}</span>
        </p>

        <form method="POST" action="{{ route('logout') }}" class="mt-2">
            @csrf
            <button type="submit" class="whale-row whale-row-hover px-3 py-2.5 text-rose-600 dark:text-rose-400">
                <x-ui.icon name="arrow-right" class="h-4 w-4 shrink-0 rotate-180" />
                <span>Sign out</span>
            </button>
        </form>
    </div>
</aside>
