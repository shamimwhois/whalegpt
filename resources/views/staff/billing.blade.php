@extends('layouts.app')
@section('title', 'Page')
@section('content')
<div class="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6">
        <h1 class="text-2xl font-semibold text-[#0d0d0d] dark:text-[#ececec]">Billing overview</h1>
        <p class="mt-2 text-sm text-black/60 dark:text-white/60">
            Staff only. This surface is not a customer account.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            @foreach (\App\Billing\Plan::cases() as $plan)
                <div class="rounded-2xl border border-black/[0.08] p-5 dark:border-white/[0.12]">
                    <p class="text-sm font-medium text-[#0d0d0d] dark:text-[#ececec]">{{ $plan->label() }}</p>
                    <p class="mt-1 text-xl font-semibold text-[#0d0d0d] dark:text-[#ececec]">{{ $plan->price() }}</p>
                    <p class="mt-2 text-xs text-black/50 dark:text-white/50">
                        {{ count($plan->features()) }} features
                    </p>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-sm text-black/60 dark:text-white/60">
            Gateway:
            <span class="font-medium">{{ config('billing.gateway') }}</span>
            —
            @if (app(\App\Billing\PaymentGateway::class)->available())
                configured
            @else
                <span class="text-amber-600 dark:text-amber-400">no credentials, checkout disabled</span>
            @endif
        </p>
    </div>
@endsection