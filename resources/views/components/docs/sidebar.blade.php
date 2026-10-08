{{--
    The documentation sidebar.

    Built from config/docs.php so a new guide appears the moment it is declared,
    never drifting out of step with the routes. It is a vertical list on desktop
    and a horizontally scrolling rail on small screens, where a column would eat
    the whole viewport.
--}}
@props([
    'current' => null,
])

@php
    $groups = [];

    foreach (config('docs.pages', []) as $slug => $page) {
        $groups[$page['group'] ?? 'Other'][] = [
            'slug' => $slug,
            'title' => $page['title'] ?? ucfirst($slug),
        ];
    }
@endphp

<aside class="lg:sticky lg:top-20 lg:self-start">
    <nav
        aria-label="Documentation"
        class="-mx-4 flex gap-4 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:px-6 lg:mx-0 lg:flex-col lg:gap-7 lg:overflow-visible lg:px-0 lg:pb-0"
    >
        <a
            href="{{ route('docs.index') }}"
            @if ($current === null) aria-current="page" @endif
            class="whale-row whale-row-hover shrink-0 whitespace-nowrap px-3 py-2 text-[13px] font-medium lg:whitespace-normal"
        >Overview</a>

        @foreach ($groups as $group => $items)
            <div class="shrink-0 lg:shrink">
                <p class="hidden px-3 text-[11px] font-semibold uppercase tracking-wide text-faint lg:block">{{ $group }}</p>

                <ul class="flex gap-1 lg:mt-2 lg:flex-col lg:gap-0.5">
                    @foreach ($items as $item)
                        <li class="shrink-0">
                            <a
                                href="{{ route('docs.show', $item['slug']) }}"
                                @if ($current === $item['slug']) aria-current="page" @endif
                                class="whale-row whale-row-hover whitespace-nowrap px-3 py-2 lg:whitespace-normal"
                            >{{ $item['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
</aside>
