@extends('layouts.app')

@section('title', $conversation->title)

@section('content')
    <div class="min-h-dvh">
        <header class="border-b border-black/[0.08] dark:border-white/[0.12]">
            <div class="mx-auto flex h-14 max-w-3xl items-center gap-2 px-4">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-accent text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                               d="M3 12c2.5-3.5 6-5.5 9-5.5S18.5 8.5 21 12c-2.5 3.5-6 5.5-9 5.5S5.5 15.5 3 12Z"/>
                    </svg>
                </span>
                <span class="text-sm font-medium">Whale</span>
                <span class="ml-auto rounded-full border border-black/[0.08] px-2.5 py-0.5 text-[11px] uppercase tracking-wide
                             text-[#8f8f8f] dark:border-white/[0.14]">
                    Shared · read-only
                </span>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-8">
            <h1 class="text-2xl font-semibold">{{ $conversation->title }}</h1>
            <p class="mt-1 text-xs text-[#8f8f8f]">
                {{ $conversation->messages()->count() }} messages · exported view, nothing here can be changed
            </p>

            <div class="mt-6 space-y-5">
                @forelse($messages as $message)
                    <article class="rounded-2xl border border-black/[0.06] px-4 py-3 dark:border-white/[0.1]">
                        <p class="text-[11px] font-semibold uppercase tracking-wide
                                  {{ $message->role === 'user' ? 'text-accent' : 'text-[#8f8f8f]' }}">
                            {{ $message->role === 'user' ? 'You' : 'Whale' }}
                        </p>
                        <div class="mt-1.5 whitespace-pre-wrap break-words text-sm leading-relaxed">{{ $message->content }}</div>
                    </article>
                @empty
                    <p class="text-sm text-[#8f8f8f]">This conversation has no messages yet.</p>
                @endforelse
            </div>

            <p class="mt-8 text-xs text-[#8f8f8f]">
                Shared from {{ config('app.name') }}. The owner can revoke this link at any time.
            </p>
        </main>
    </div>
@endsection
