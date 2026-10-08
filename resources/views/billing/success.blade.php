@extends('layouts.app')

@extends('layouts.app')
@section('title', 'Billing')
@section('content')
    <div class="mx-auto w-full max-w-xl px-4 py-16 text-center sm:px-6">
        <h1 class="text-2xl font-semibold text-[#0d0d0d] dark:text-[#ececec]">
            @if ($pending)
                Confirming your payment
            @else
                You are on {{ $plan->label() }}
            @endif
        </h1>

        <p class="mt-3 text-sm text-black/60 dark:text-white/60">
            @if ($pending)
                Your payment is still being confirmed. It usually takes a few seconds;
                this page updates on refresh.
            @else
                Your subscription is active. Manage it any time from the pricing page.
            @endif
        </p>

        <div class="mt-8 flex justify-center gap-3">
            <a href="{{ route('pricing') }}"
               class="rounded-xl border border-black/[0.12] px-4 py-2 text-sm font-medium transition hover:bg-black/[0.04] dark:border-white/[0.15] dark:hover:bg-white/10">
                Back to pricing
            </a>
            <a href="{{ route('chat.index') }}"
               class="rounded-xl bg-[#0d0d0d] px-4 py-2 text-sm font-medium text-white transition hover:bg-black/85 dark:bg-[#ececec] dark:text-[#0d0d0d]">
                Open the app
            </a>
        </div>
    </div>
@endsection