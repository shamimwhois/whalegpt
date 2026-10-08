@extends('layouts.docs')

@section('title', 'Documentation · '.config('app.name'))
@section('description', 'Guides and API references for Whale AI.')

@section('doc')
    <div class="max-w-3xl">
        <h1 class="text-3xl font-semibold tracking-[-0.03em] sm:text-4xl">Documentation</h1>
        <p class="mt-4 text-base leading-relaxed text-body">
            Everything you need to sign in, send a message and read the response. The guides here
            describe the endpoints the chat interface itself calls, so what you read is what runs.
        </p>

        @php
            // Loop keys are deliberately named away from `$slug`/`$page`:
            // Blade renders the child before the parent, so a `$slug` assigned
            // here would leak into layouts/docs and mark the wrong sidebar
            // entry as the current page.
            $groups = [];
            foreach ($pages as $pageKey => $pageEntry) {
                $groups[$pageEntry['group'] ?? 'Other'][] = ['slug' => $pageKey, 'page' => $pageEntry];
            }
        @endphp

        @foreach ($groups as $group => $items)
            <div class="mt-10">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-faint">{{ $group }}</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($items as $item)
                        <a
                            href="{{ route('docs.show', $item['slug']) }}"
                            class="group rounded-2xl border border-line bg-raised p-5 transition hover:border-line-strong hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        >
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-semibold tracking-[-0.01em]">{{ $item['page']['title'] }}</h3>
                                <x-ui.icon name="arrow-right" class="h-4 w-4 text-faint transition group-hover:translate-x-0.5 group-hover:text-accent" />
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-body">{{ $item['page']['summary'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endsection
