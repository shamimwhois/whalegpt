@extends('layouts.marketing')

@section('title', 'Whale AI — a local-first AI assistant')
@section('description', 'Chat, code, research and create with Whale AI. Runs on the models you already have, with speech, vision and media built in.')

@section('content')

    {{-- Hero. A deep-ocean panel in both themes: the one place the page commits
         to the whale metaphor, so the rest of the page can stay quiet. --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 -z-20 bg-gradient-to-b from-[#03252b] via-[#053b42] to-[#07575d]"></div>

        {{-- Sonar rings and a soft glow, purely decorative. --}}
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
            <div class="absolute -left-24 top-10 h-72 w-72 rounded-full bg-accent/20 blur-3xl"></div>
            <div class="absolute -right-16 bottom-0 h-80 w-80 rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="absolute left-1/2 top-1/3 h-[38rem] w-[38rem] -translate-x-1/2 rounded-full border border-white/5"></div>
            <div class="absolute left-1/2 top-1/3 h-[26rem] w-[26rem] -translate-x-1/2 rounded-full border border-white/10"></div>
        </div>

        <div class="mx-auto grid max-w-6xl items-center gap-14 px-4 pb-24 pt-16 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:gap-10 lg:px-8 lg:pb-32 lg:pt-24">
            <div class="whale-rise text-white">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-white/80 backdrop-blur">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                    Local-first · Bring your own models
                </span>

                <h1 class="mt-6 text-4xl font-semibold leading-[1.05] tracking-[-0.03em] sm:text-5xl lg:text-6xl">
                    Intelligence that
                    <span class="bg-gradient-to-r from-accent to-cyan-300 bg-clip-text text-transparent">goes deep</span>
                    without leaving your machine.
                </h1>

                <p class="mt-6 max-w-xl text-base leading-relaxed text-white/70 sm:text-lg">
                    Whale AI is a single workspace for chat, code, research, speech and media. Point it at
                    Ollama, llama.cpp or any OpenAI-compatible endpoint and keep every conversation on your own hardware.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    @auth
                        <a href="{{ route('chat.index') }}"
                           class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-5 py-3 text-sm font-semibold text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/70">
                            Open the app
                            <x-ui.icon name="arrow-right" class="h-4 w-4" />
                        </a>
                    @else
                        <button type="button" x-on:click="authTab = 'register'; authOpen = true"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-5 py-3 text-sm font-semibold text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/70">
                            Get started free
                            <x-ui.icon name="arrow-right" class="h-4 w-4" />
                        </button>
                    @endauth

                    <a href="{{ route('docs.index') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/5 px-5 py-3 text-sm font-semibold text-white/90 backdrop-blur transition hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/70">
                        Read the docs
                    </a>
                </div>

                <dl class="mt-10 grid max-w-lg grid-cols-3 gap-6 border-t border-white/10 pt-6">
                    <div>
                        <dt class="text-2xl font-semibold tracking-[-0.02em]">7</dt>
                        <dd class="mt-1 text-xs text-white/60">Assistant modes</dd>
                    </div>
                    <div>
                        <dt class="text-2xl font-semibold tracking-[-0.02em]">100%</dt>
                        <dd class="mt-1 text-xs text-white/60">Local by default</dd>
                    </div>
                    <div>
                        <dt class="text-2xl font-semibold tracking-[-0.02em]">∞</dt>
                        <dd class="mt-1 text-xs text-white/60">Providers</dd>
                    </div>
                </dl>
            </div>

            {{-- Product preview: a stylised transcript, not a screenshot, so it
                 stays crisp at any size and needs no binary asset. --}}
            <div class="whale-rise relative lg:justify-self-end">
                <div class="absolute -inset-6 -z-10 rounded-[2rem] bg-white/5 blur-2xl"></div>

                <div class="w-full max-w-md overflow-hidden rounded-2xl border border-white/12 bg-[#0b3b41]/80 shadow-2xl backdrop-blur">
                    <div class="flex items-center gap-1.5 border-b border-white/10 px-4 py-3">
                        <span class="h-2.5 w-2.5 rounded-full bg-rose-400/70"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-300/70"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/70"></span>
                        <span class="ml-2 text-[11px] font-medium text-white/50">whale · chat</span>
                    </div>

                    <div class="space-y-4 px-4 py-5 text-sm">
                        <div class="ml-auto max-w-[80%] rounded-2xl rounded-br-md bg-accent px-3.5 py-2.5 text-white">
                            Summarise this release note and draft a changelog entry.
                        </div>
                        <div class="max-w-[88%] rounded-2xl rounded-bl-md bg-white/8 px-3.5 py-3 text-white/85">
                            <p class="text-white/60">Delegating to <span class="font-medium text-white">coding_agent</span>…</p>
                            <p class="mt-2 leading-relaxed">Added streaming responses, on-device speech, and a sandboxed workspace. Nothing left the machine.</p>
                        </div>
                        <div class="flex items-center gap-2 pt-1 text-[11px] text-white/40">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-accent"></span>
                            responding on <span class="font-medium text-white/60">qwen3:4b</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Wave divider into the page background. --}}
        <svg class="relative block w-full text-page" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
            <path fill="currentColor" d="M0 60c180 30 360 30 540 0s360-30 540 0 360 22 360 22V90H0Z"></path>
        </svg>
    </section>

    {{-- Features. Six cards on one grain; the grid steps 1 → 2 → 3 columns. --}}
    @php
        $features = [
            ['icon' => 'sparkles', 'title' => 'Seven assistant modes', 'body' => 'Chat, plan, code, debug, research, study and art. Each mode gets exactly the tools it needs, and the read-only ones cannot touch a file.'],
            ['icon' => 'bolt', 'title' => 'Streams as it thinks', 'body' => 'Responses arrive token by token over the Vercel data protocol, so a long answer starts appearing immediately instead of after it finishes.'],
            ['icon' => 'globe', 'title' => 'Research with sources', 'body' => 'Turn on web search or deep search and the assistant plans multiple queries, cross-checks pages and cites what it found.'],
            ['icon' => 'folder', 'title' => 'A real workspace', 'body' => 'Read, write, search and run files in a sandboxed project tree, with a file editor and a terminal built into the same window.'],
            ['icon' => 'image', 'title' => 'Speech, vision and media', 'body' => 'Transcribe the microphone on-device, describe a camera frame, or generate images, audio and video from any capable provider.'],
            ['icon' => 'sliders', 'title' => 'Bring your own models', 'body' => 'Ollama, llama.cpp, local GGUF and safetensors files and any OpenAI-compatible endpoint all show up in the same picker, described honestly.'],
        ];
    @endphp

    <section id="features" class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-wide text-accent">Capabilities</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-[-0.03em] sm:text-4xl">
                One workspace, every kind of work.
            </h2>
            <p class="mt-4 text-base leading-relaxed text-body">
                Whale AI is not a chat box with a theme. It is a full surface for the tasks that usually
                send you to five different tools — and it runs on your own hardware.
            </p>
        </div>

        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as $feature)
                <div class="group rounded-2xl border border-line bg-raised p-6 transition hover:border-line-strong hover:shadow-lg">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-accent/10 text-accent transition group-hover:bg-accent group-hover:text-white">
                        <x-ui.icon :name="$feature['icon']" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-4 text-base font-semibold tracking-[-0.01em]">{{ $feature['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-body">{{ $feature['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it works. Three steps, numbered, in one row on desktop. --}}
    @php
        $steps = [
            ['n' => '01', 'title' => 'Connect a model', 'body' => 'Point Whale at Ollama, a llama.cpp server, a local GGUF file, or paste any OpenAI-compatible endpoint. Keys never leave the server.'],
            ['n' => '02', 'title' => 'Pick a mode', 'body' => 'Choose chat for conversation, code to edit files, research to search the web, or art to draw. The picker only offers what this install can actually run.'],
            ['n' => '03', 'title' => 'Work, and keep it', 'body' => 'Conversations, projects and a sandboxed workspace stay scoped to your browser. Export any thread as text, markdown, HTML, JSON or PDF.'],
        ];
    @endphp

    <section id="how" class="border-y border-line bg-sunken">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-accent">How it works</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-[-0.03em] sm:text-4xl">
                    From zero to a working assistant in three steps.
                </h2>
            </div>

            <div class="mt-12 grid gap-8 lg:grid-cols-3">
                @foreach ($steps as $step)
                    <div class="relative">
                        <span class="font-mono text-sm font-semibold text-accent">{{ $step['n'] }}</span>
                        <h3 class="mt-3 text-lg font-semibold tracking-[-0.01em]">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-body">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Closing call to action, back in the hero's ocean palette. --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#03252b] via-[#053b42] to-[#07575d] px-6 py-14 text-center sm:px-12 lg:py-20">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -left-10 -top-10 h-56 w-56 rounded-full bg-accent/20 blur-3xl"></div>
                <div class="absolute -bottom-16 right-0 h-64 w-64 rounded-full bg-cyan-400/10 blur-3xl"></div>
            </div>

            <div class="relative">
                <x-ui.logo :wordmark="false" class="mx-auto h-12 w-12 text-white" />

                <h2 class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-white sm:text-4xl">
                    Dive in.
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-white/70">
                    Sign in with Google or GitHub, or create an account with your email. No credit card,
                    and your conversations stay yours.
                </p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    @auth
                        <a href="{{ route('chat.index') }}"
                           class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-6 py-3 text-sm font-semibold text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/70">
                            Open the app
                            <x-ui.icon name="arrow-right" class="h-4 w-4" />
                        </a>
                    @else
                        <button type="button" x-on:click="authTab = 'register'; authOpen = true"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-6 py-3 text-sm font-semibold text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/70">
                            Create your account
                            <x-ui.icon name="arrow-right" class="h-4 w-4" />
                        </button>
                    @endauth

                    <a href="{{ route('docs.index') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-6 py-3 text-sm font-semibold text-white/90 transition hover:bg-white/10">
                        Explore the docs
                    </a>
                </div>
            </div>
        </div>
    </section>

@endsection
