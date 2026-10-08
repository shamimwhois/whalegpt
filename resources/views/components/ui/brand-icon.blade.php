{{--
    Provider marks for the authentication modal.

    Kept apart from x-ui.icon because these are filled brand glyphs with their
    own fill rules, whereas x-ui.icon draws stroked line art from one map.

        <x-ui.brand-icon name="google" class="h-5 w-5" />
--}}
@props([
    'name',
    'class' => 'h-5 w-5',
])

@php
    $icons = [
        'google' => '<path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.05H12v3.88h5.38a4.6 4.6 0 0 1-2 3.02v2.5h3.24c1.9-1.74 2.98-4.3 2.98-7.35Z"/><path fill="#34A853" d="M12 22c2.7 0 4.96-.9 6.62-2.42l-3.24-2.5c-.9.6-2.05.96-3.38.96-2.6 0-4.8-1.76-5.58-4.12H3.06v2.6A10 10 0 0 0 12 22Z"/><path fill="#FBBC05" d="M6.42 13.92a6 6 0 0 1 0-3.84v-2.6H3.06a10 10 0 0 0 0 9.04l3.36-2.6Z"/><path fill="#EA4335" d="M12 5.96c1.47 0 2.78.5 3.82 1.5l2.86-2.86C16.95 2.98 14.7 2 12 2A10 10 0 0 0 3.06 7.48l3.36 2.6C7.2 7.72 9.4 5.96 12 5.96Z"/>',
        'github' => '<path fill="currentColor" d="M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48l-.01-1.7c-2.78.6-3.37-1.34-3.37-1.34-.45-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.9 1.53 2.36 1.09 2.94.83.09-.65.35-1.09.63-1.34-2.22-.25-4.55-1.11-4.55-4.94 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.65 0 0 .84-.27 2.75 1.02a9.5 9.5 0 0 1 5 0c1.91-1.29 2.75-1.02 2.75-1.02.55 1.38.2 2.4.1 2.65.64.7 1.03 1.59 1.03 2.68 0 3.84-2.34 4.69-4.57 4.94.36.31.68.92.68 1.85l-.01 2.74c0 .27.18.58.69.48A10 10 0 0 0 12 2Z"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $icons[$name] ?? '' !!}</svg>
