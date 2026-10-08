{{--
    The pricing page.

    Public on purpose: a visitor has to be able to read what a plan costs before
    deciding whether to sign in. What each tier unlocks comes from
    \App\Billing\Plan::features() rather than being written out here, so the page
    can never drift from the middleware that enforces it.

    Layout notes: the cards collapse 3 -> 2 -> 1 across breakpoints, and the
    comparison below is built from flex rows rather than a <table> so a narrow
    screen wraps each feature onto its own line instead of scrolling sideways.
--}}
@extends('layouts.app')

@section('title', 'Pricing')

@section('content')
<div class="min-h-full bg-[#f7f7f8] dark:bg-[#171717]">
    <div class="mx-auto w-full max-w-6xl px-4 pb-20 pt-14 sm:px-6 sm:pt-20 lg:px-8">
        {{-- Hero --}}
        <header class="mx-auto max-w-2xl text-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-black/[0.08] bg-white px-3 py-1 text-xs font-medium text-black/60 dark:border-white/[0.12] dark:bg-white/[0.04] dark:text-white/60">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Cancel any time
            </span>

            <h1 class="mt-5 text-4xl font-semibold tracking-tight text-[#0d0d0d] dark:text-[#ececec] sm:text-5xl">
                Pricing that scales with you
            </h1>
            <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-black/60 dark:text-white/60 sm:text-lg">
                Start free. Upgrade when you need voice, the camera or local models —
                your chats and projects stay where they are.
            </p>
        </header>

        {{-- Notices. flex-wrap keeps more than one alert on a row when there is
             room and stacks them cleanly when there is not. --}}
        <div class="mx-auto mt-8 flex max-w-2xl flex-col items-center gap-3">
            @if ($canceled)
                <p class="w-full rounded-xl bg-amber-50 px-4 py-3 text-center text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    Checkout was cancelled. Nothing was charged.
                </p>
            @endif

            @if (session('error'))
                <p class="w-full rounded-xl bg-red-50 px-4 py-3 text-center text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300" role="alert">
                    {{ session('error') }}
                </p>
            @endif

            @unless ($gateway)
                <p class="w-full rounded-xl bg-white px-4 py-3 text-center text-sm text-black/60 ring-1 ring-black/[0.06] dark:bg-white/[0.04] dark:text-white/60 dark:ring-white/[0.08]">
                    Payments are not configured on this deployment yet, so checkout is
                    unavailable. The tiers below still describe what each plan unlocks.
                </p>
            @endunless
        </div>

        {{-- Plans. Two columns once there is room for them, three on wide screens;
             the featured card keeps its place rather than being reordered. --}}
        <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($plans as $plan)
                @php
                    $isCurrent = $current === $plan;
                    $featured = $plan === \App\Billing\Plan::Pro;
                @endphp

                <section @class([
                    'relative flex flex-col rounded-2xl border p-6 transition',
                    'border-black/[0.08] bg-white dark:border-white/[0.12] dark:bg-[#212121]',
                    'shadow-sm hover:shadow-md',
                    'ring-2 ring-[#0d0d0d] dark:ring-[#ececec]' => $featured,
                ])>
                    @if ($featured)
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-[#0d0d0d] px-3 py-1 text-xs font-medium text-white dark:bg-[#ececec] dark:text-[#0d0d0d]">
                            Most popular
                        </span>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-semibold text-[#0d0d0d] dark:text-[#ececec]">
                            {{ $plan->label() }}
                        </h2>
                        @if ($isCurrent)
                            <span class="rounded-full bg-black/[0.06] px-2 py-0.5 text-[11px] font-medium text-black/60 dark:bg-white/[0.1] dark:text-white/60">
                                Current
                            </span>
                        @endif
                    </div>

                    <p class="mt-4 flex flex-wrap items-baseline gap-1.5 text-[2.5rem] font-semibold leading-none tracking-tight text-[#0d0d0d] dark:text-[#ececec]">
                        <span>{{ $plan->priceInCents() === 0 ? '$0' : '$'.number_format($plan->priceInCents() / 100, 0) }}</span>
                        <span class="text-sm font-normal text-black/45 dark:text-white/45">
                            {{ $plan->priceInCents() === 0 ? 'forever' : '/month' }}
                        </span>
                    </p>

                    <p class="mt-3 text-sm leading-relaxed text-black/55 dark:text-white/55">
                        @switch($plan)
                            @case(\App\Billing\Plan::Free)
                                Chat and images, on your own keys. No card needed.
                                @break
                            @case(\App\Billing\Plan::Pro)
                                Voice, the camera, agents and the workspace IDE.
                                @break
                            @default
                                Everything in Pro, plus local models and priority routing.
                        @endswitch
                    </p>

                    <ul class="mt-6 flex-1 space-y-2.5 text-sm text-black/70 dark:text-white/70">
                        @foreach ($plan->features() as $feature)
                            <li class="flex items-start gap-2.5">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-black/40 dark:text-white/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                                <span class="min-w-0 break-words">{{ str_replace('_', ' ', $feature) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-7">
                        @if ($isCurrent)
                            <span class="block rounded-xl bg-black/[0.06] px-4 py-2.5 text-center text-sm font-medium text-black/55 dark:bg-white/[0.08] dark:text-white/55">
                                Your current plan
                            </span>
                        @elseif ($gateway && auth()->check() && $plan !== \App\Billing\Plan::Free)
                            <form method="POST" action="{{ route('billing.checkout') }}">
                                @csrf
                                <input type="hidden" name="plan" value="{{ $plan->value }}">
                                <button type="submit"
                                        class="w-full rounded-xl bg-[#0d0d0d] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-black/85 dark:bg-[#ececec] dark:text-[#0d0d0d] dark:hover:bg-white">
                                    Upgrade to {{ $plan->label() }}
                                </button>
                            </form>
                        @elseif ($gateway && auth()->check())
                            <a href="{{ route('chat.index') }}"
                               class="block rounded-xl border border-black/[0.12] px-4 py-2.5 text-center text-sm font-medium transition hover:bg-black/[0.04] dark:border-white/[0.15] dark:hover:bg-white/10">
                                Back to the app
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="block rounded-xl bg-[#0d0d0d] px-4 py-2.5 text-center text-sm font-medium text-white transition hover:bg-black/85 dark:bg-[#ececec] dark:text-[#0d0d0d] dark:hover:bg-white">
                                {{ $plan->priceInCents() === 0 ? 'Start free' : 'Sign in to upgrade' }}
                            </a>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Everything that appears in any tier, de-duplicated so the comparison is
             driven by the data rather than by a hand-written list that could
             drift. --}}
        @php
            $allFeatures = collect($plans)
                ->flatMap(fn ($plan) => $plan->features())
                ->unique()
                ->values();
        @endphp

        <section class="mt-16">
            <h2 class="text-center text-xl font-semibold text-[#0d0d0d] dark:text-[#ececec]">
                Compare every feature
            </h2>
            <p class="mt-2 text-center text-sm text-black/55 dark:text-white/55">
                What each tier includes, feature by feature.
            </p>

            {{-- A flex row per feature rather than a <table>: the plan labels
                 sit on the right and wrap under the feature name on a narrow
                 screen, so nothing is ever cut off or needs sideways scrolling. --}}
            <div class="mx-auto mt-8 max-w-3xl divide-y divide-black/[0.06] overflow-hidden rounded-2xl border border-black/[0.08] bg-white dark:divide-white/[0.08] dark:border-white/[0.12] dark:bg-[#212121]">
                <div class="flex flex-wrap items-center justify-between gap-3 bg-black/[0.02] px-4 py-3 text-xs font-medium uppercase tracking-wide text-black/50 dark:bg-white/[0.03] dark:text-white/50 sm:px-5">
                    <span>Feature</span>
                    <span class="flex flex-wrap justify-end gap-3 sm:gap-6">
                        @foreach ($plans as $plan)
                            <span class="w-16 text-center sm:w-20">{{ $plan->label() }}</span>
                        @endforeach
                    </span>
                </div>

                @foreach ($allFeatures as $feature)
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3 text-sm sm:px-5">
                        <span class="min-w-0 flex-1 break-words text-black/75 dark:text-white/75">
                            {{ str_replace('_', ' ', $feature) }}
                        </span>
                        <span class="flex shrink-0 flex-wrap justify-end gap-3 sm:gap-6">
                            @foreach ($plans as $plan)
                                <span class="flex w-16 items-center justify-center sm:w-20">
                                    @if ($plan->allows($feature))
                                        <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-label="Included">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                        </svg>
                                    @else
                                        <span class="h-px w-3 bg-black/20 dark:bg-white/20" aria-label="Not included"></span>
                                    @endif
                                </span>
                            @endforeach
                        </span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Your own account, only once signed in. --}}
        @auth
            <section class="mt-16 rounded-2xl border border-black/[0.08] bg-white p-6 dark:border-white/[0.12] dark:bg-[#212121]">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-[#0d0d0d] dark:text-[#ececec]">
                            Your account
                        </h2>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-black/55 dark:text-white/55">
                            <span class="rounded-full bg-black/[0.05] px-2 py-0.5 text-xs font-medium dark:bg-white/[0.08]">
                                {{ auth()->user()->plan()->label() }}
                            </span>
                            <span>{{ auth()->user()->role()->label() }}</span>
                            <span aria-hidden="true">·</span>
                            <span class="truncate">{{ auth()->user()->email }}</span>
                        </p>
                    </div>

                    @if ($gateway)
                        <form method="POST" action="{{ route('billing.portal') }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="rounded-xl border border-black/[0.12] px-4 py-2 text-sm font-medium transition hover:bg-black/[0.04] dark:border-white/[0.15] dark:hover:bg-white/10">
                                Manage billing
                            </button>
                        </form>
                    @endif
                </div>
            </section>
        @endauth

        {{-- Reassurance strip. flex-wrap lets these sit on one line on a desktop
             and fold to a column on a phone without a separate layout. --}}
        <ul class="mt-14 flex flex-wrap items-center justify-center gap-x-8 gap-y-4 text-sm text-black/55 dark:text-white/55">
            @foreach ([
                'Cancel any time',
                'Your chats are never deleted',
                'Bring your own API keys',
                'Local models stay on your machine',
            ] as $note)
                <li class="flex items-center gap-2">
                    <svg class="h-4 w-4 shrink-0 text-black/35 dark:text-white/35" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25 4.5-4.5"/>
                    </svg>
                    {{ $note }}
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection