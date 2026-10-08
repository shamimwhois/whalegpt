@extends('layouts.docs')

@section('title', ($page['title'] ?? 'Documentation').' · '.config('app.name'))
@section('description', $page['summary'] ?? 'Whale AI documentation.')

@section('doc')
    <article class="prose-md max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-wide text-accent">{{ $page['group'] ?? '' }}</p>
        <h1 class="!mt-2 text-3xl font-semibold tracking-[-0.03em] sm:text-4xl">{{ $page['title'] }}</h1>
        <p class="mt-4 text-base leading-relaxed text-body">{{ $page['summary'] }}</p>

        @include('pages.docs.content.'.$slug)
    </article>
@endsection
