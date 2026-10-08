{{--
    The Whale AI wordmark.

    One whale silhouette and, optionally, the product name beside it. The mark
    inherits `currentColor` so it can sit on the dark hero or the light header
    without a second asset.

        <x-ui.logo />
        <x-ui.logo :wordmark="false" class="h-9 w-9" />
--}}
@props([
    'class' => 'h-8 w-8',
    'wordmark' => true,
])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg class="{{ $class }}" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
        {{-- Body: a rounded mass that tapers to the fluke on the right. --}}
        <path
            d="M2.6 17.4c3.7 0 6.6-1.3 8.7-3.9C13 10.7 15.8 8.8 19.3 8.8c4.4 0 7.6 3.2 7.6 7.5 0 .9.3 1.6.9 2.1.6.5 1.5.8 2.7.8-.7 2-2.6 3.2-4.8 3.2-1.4 0-2.6-.4-3.6-1.2-1.2 1.7-3.4 2.8-6 2.8-4.3 0-7.9-2.9-8.9-6.7H2.6Z"
            fill="currentColor"
        />
        {{-- Fluke. --}}
        <path d="M26.4 15.6c1.4-1.1 2.6-1.9 3.9-2.4-.3 1.6-.3 3.2 0 4.7-1.3-.5-2.6-1.3-3.9-2.3Z" fill="currentColor" />
        {{-- Eye, punched in the page colour so it reads on either theme. --}}
        <circle cx="23.2" cy="13.3" r="1.15" fill="currentColor" opacity="0.35" />
    </svg>

    @if ($wordmark)
        <span class="text-[15px] font-semibold tracking-[-0.02em]">{{ config('app.name') }}</span>
    @endif
</span>
