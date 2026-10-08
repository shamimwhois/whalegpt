<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[#f7f7f8] text-[#0d0d0d] antialiased dark:bg-[#171717] dark:text-[#ececec]">
    <main class="flex min-h-full items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <h1 class="mb-1 text-center text-xl font-semibold">{{ config('app.name') }}</h1>
            <p class="mb-6 text-center text-sm text-black/50 dark:text-white/50">
                Enter the email you want to use for this workspace.
            </p>

            <form method="POST" action="{{ route('login.store') }}"
                  class="space-y-4 rounded-2xl border border-black/[0.08] bg-white p-6 shadow-sm
                         dark:border-white/[0.12] dark:bg-[#212121]">
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        class="w-full rounded-xl border border-black/[0.12] bg-white px-3 py-2 text-sm
                               outline-none transition focus:border-black/40 focus:ring-2 focus:ring-black/10
                               dark:border-white/15 dark:bg-[#171717] dark:focus:border-white/40"
                        placeholder="you@example.com"
                    >
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-[#0d0d0d] px-4 py-2.5 text-sm font-medium text-white
                           transition hover:bg-black/85 dark:bg-[#ececec] dark:text-[#0d0d0d] dark:hover:bg-white"
                >Continue</button>
            </form>

            <p class="mt-4 text-center text-xs text-black/40 dark:text-white/40">
                The account is created automatically on first sign-in.
            </p>
        </div>
    </main>
</body>
</html>
