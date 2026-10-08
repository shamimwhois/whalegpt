{{-- A one-line trail: Documentation / <group> / <page>. --}}
@props(['slug' => null])

@php
    $page = $slug !== null ? (config('docs.pages')[$slug] ?? null) : null;
@endphp

<nav aria-label="Breadcrumb" class="mb-5 flex items-center gap-1.5 text-xs text-muted">
    <a href="{{ route('docs.index') }}" class="transition hover:text-ink">Documentation</a>

    @if ($page)
        <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-faint" />
        <span>{{ $page['group'] ?? '' }}</span>
        <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-faint" />
        <span class="font-medium text-ink">{{ $page['title'] ?? '' }}</span>
    @endif
</nav>
