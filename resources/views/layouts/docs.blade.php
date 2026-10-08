{{--
    The documentation shell.

    Extends the marketing layout so the docs inherit the same header, footer,
    theme handling and auth modal, then adds the two-column reading surface:
    a grouped sidebar that becomes a horizontal rail on small screens, and the
    article itself. The article is rendered by the child via @section('doc').
--}}
@extends('layouts.marketing')

@php
    $docPages = config('docs.pages', []);
    $currentSlug = $slug ?? null;
@endphp

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-14">
        <div class="lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-12">
            <x-docs.sidebar :current="$currentSlug" />

            <div class="mt-8 min-w-0 lg:mt-0">
                <x-docs.breadcrumb :slug="$currentSlug" />
                @yield('doc')
            </div>
        </div>
    </div>
@endsection
