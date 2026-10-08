{{--
    The one place a glyph is drawn.

    The chat views had accumulated the same twenty-odd paths inline, several of
    them three or four times over, which meant a change to one copy silently
    left the others behind. Naming them here makes the set reviewable and lets
    a stroke width or a viewBox change happen once.

        <x-ui.icon name="pin" />
        <x-ui.icon name="pin" class="h-5 w-5 text-accent" />

    `name` is matched against the map below. An unknown name throws in
    development rather than rendering an empty box, because a silently missing
    icon is much harder to notice than a build-time failure.
--}}
@props([
    'name',
    'class' => 'h-4 w-4',
])

@php
    $icons = [
        // Navigation
        'menu' => '<path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>',
        'chevron-down' => '<path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>',
        'chevron-right' => '<path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>',
        'more' => '<path stroke-linecap="round" d="M6 12h.01M12 12h.01M18 12h.01"/>',
        'plus' => '<path stroke-linecap="round" d="M12 5v14M5 12h14"/>',
        'minus' => '<path stroke-linecap="round" d="M5 12h14"/>',
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>',
        'arrow-right' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>',
        'arrow-down' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m0 0 6-6m-6 6-6-6"/>',
        'external' => '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>',

        // Surfaces
        'sparkles' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>',
        'image' => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/>',
        'folder' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>',
        'grid' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM4 10h16M10 10v9"/>',
        'list' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h11.5M8.25 12h11.5M8.25 17.25h11.5M4.5 6.75h.01M4.5 12h.01M4.5 17.25h.01"/>',
        'archive' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 5.25h18v3.5H3zM4.5 8.75v10a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-10M9.75 12.5h4.5"/>',
        'sliders' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/>',

        // Actions
        'search' => '<circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
        'bolt' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/>',
        'pencil' => '<path stroke-linecap="round" stroke-linejoin="round" d="m16.9 4.5 2.6 2.6L8.6 18 5 19l1-3.6Z"/>',
        'trash' => '<path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>',
        'pin' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l-1 7 3 3H7l3-3-1-7Z"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path stroke-linecap="round" d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'download' => '<path stroke-linecap="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M12 4v12m0 0-4-4m4 4 4-4"/>',
        'refresh' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>',
        'stop' => '<rect x="6" y="6" width="12" height="12" rx="1.5"/>',
        'link' => '<path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/>',

        // Signals and toggles
        'thumb-up' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904M14.25 9h2.25M5.904 18.75c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path stroke-linecap="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>',
        'keyboard' => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><path stroke-linecap="round" d="M6.5 10h.01M10 10h.01M13.5 10h.01M17 10h.01M8 14h8"/>',
        'type' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5h16v2M9 19h6M12 5v14"/>',
        'rows' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>',
        'shield' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>',
    ];
@endphp

@if (! array_key_exists($name, $icons))
    @throw(new \InvalidArgumentException("Unknown icon [{$name}]."))
@endif

<svg
    {{ $attributes->merge(['class' => $class]) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    aria-hidden="true"
    focusable="false"
>{!! $icons[$name] !!}</svg>