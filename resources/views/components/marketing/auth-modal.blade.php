{{--
    The authentication modal.

    Opened from the header (Sign in / Get started) or automatically when a form
    POST comes back with validation errors, so a failed attempt lands the user
    back where they were. Google and GitHub are one-click; email and password
    are the fallback that still works on an install with no OAuth credentials.

    A provider with no keys is shown disabled with the reason in its title,
    rather than as a redirect that cannot complete.
--}}
@props([
    'show' => 'authOpen',
])

@php
    $providers = [
        [
            'name' => 'google',
            'label' => 'Google',
            'configured' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
        ],
        [
            'name' => 'github',
            'label' => 'GitHub',
            'configured' => filled(config('services.github.client_id')) && filled(config('services.github.client_secret')),
        ],
    ];

    $inputClass = 'w-full rounded-xl border border-line bg-raised px-3 py-2.5 text-sm text-ink outline-none '
        .'transition placeholder:text-faint focus:border-accent focus:ring-2 focus:ring-accent/25';
@endphp

<div
    x-show="{{ $show }}"
    style="display:none"
    x-on:keydown.escape.window="{{ $show }} = false"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    aria-labelledby="auth-heading"
>
    <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
        <div
            class="fixed inset-0 bg-black/50 backdrop-blur-sm"
            x-on:click="{{ $show }} = false"
            aria-hidden="true"
        ></div>

        <div
            x-show="{{ $show }}"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            class="relative w-full max-w-md overflow-hidden rounded-2xl border border-line bg-popover shadow-2xl"
        >
            <button
                type="button"
                x-on:click="{{ $show }} = false"
                class="whale-icon-button absolute right-3 top-3 h-9 w-9"
                aria-label="Close"
            >
                <x-ui.icon name="close" class="h-4 w-4" />
            </button>

            <div class="px-6 pb-6 pt-7 sm:px-7">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent/10 text-accent">
                        <x-ui.logo :wordmark="false" class="h-6 w-6" />
                    </span>
                    <div>
                        <h2 id="auth-heading" class="text-base font-semibold tracking-[-0.01em]">
                            <span x-show="authTab === 'login'">Welcome back</span>
                            <span x-show="authTab === 'register'" style="display:none">Create your account</span>
                        </h2>
                        <p class="text-xs text-muted">
                            <span x-show="authTab === 'login'">Sign in to continue to {{ config('app.name') }}.</span>
                            <span x-show="authTab === 'register'" style="display:none">Start in seconds.</span>
                        </p>
                    </div>
                </div>

                @if (session('auth_error'))
                    <div class="mt-5 rounded-xl border border-rose-500/25 bg-rose-500/[0.07] px-3 py-2.5 text-xs text-rose-600 dark:text-rose-400" role="alert">
                        {{ session('auth_error') }}
                    </div>
                @endif

                <div class="mt-5 grid gap-2">
                    @foreach ($providers as $provider)
                        @if ($provider['configured'])
                            <a
                                href="{{ route('auth.redirect', $provider['name']) }}"
                                class="inline-flex w-full items-center justify-center gap-2.5 rounded-xl border border-line bg-raised px-4 py-2.5 text-sm font-medium text-ink transition hover:bg-wash focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            >
                                <x-ui.brand-icon :name="$provider['name']" class="h-5 w-5" />
                                Continue with {{ $provider['label'] }}
                            </a>
                        @else
                            <button
                                type="button"
                                disabled
                                title="{{ strtoupper($provider['name']) }} credentials are not set on this install."
                                class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2.5 rounded-xl border border-line bg-raised px-4 py-2.5 text-sm font-medium text-faint"
                            >
                                <x-ui.brand-icon :name="$provider['name']" class="h-5 w-5 opacity-60" />
                                Continue with {{ $provider['label'] }}
                            </button>
                        @endif
                    @endforeach
                </div>

                <div class="my-5 flex items-center gap-3">
                    <span class="h-px flex-1 bg-line"></span>
                    <span class="text-[11px] font-medium uppercase tracking-wide text-faint">or</span>
                    <span class="h-px flex-1 bg-line"></span>
                </div>

                <div class="mb-4 grid grid-cols-2 gap-1 rounded-xl bg-sunken p-1" role="tablist" aria-label="Sign in or create an account">
                    <button
                        type="button"
                        role="tab"
                        x-on:click="authTab = 'login'"
                        :aria-selected="authTab === 'login' ? 'true' : 'false'"
                        :class="authTab === 'login' ? 'bg-raised text-ink shadow-sm' : 'text-muted hover:text-ink'"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition"
                    >Sign in</button>
                    <button
                        type="button"
                        role="tab"
                        x-on:click="authTab = 'register'"
                        :aria-selected="authTab === 'register' ? 'true' : 'false'"
                        :class="authTab === 'register' ? 'bg-raised text-ink shadow-sm' : 'text-muted hover:text-ink'"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition"
                    >Create account</button>
                </div>

                <form x-show="authTab === 'login'" method="POST" action="{{ route('auth.login') }}" class="space-y-3">
                    @csrf

                    <div>
                        <label for="auth-login-email" class="mb-1.5 block text-xs font-medium text-body">Email</label>
                        <input id="auth-login-email" name="email" type="email" value="{{ old('email') }}" required
                               autocomplete="email" placeholder="you@example.com" class="{{ $inputClass }}">
                        @error('email')
                            <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="auth-login-password" class="mb-1.5 block text-xs font-medium text-body">Password</label>
                        <input id="auth-login-password" name="password" type="password" required
                               autocomplete="current-password" placeholder="Your password" class="{{ $inputClass }}">
                        @error('password')
                            <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 text-xs text-body">
                        <input type="checkbox" name="remember" value="1" checked
                               class="h-4 w-4 rounded border-line text-accent focus:ring-accent/30">
                        Keep me signed in
                    </label>

                    <button type="submit"
                            class="w-full rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                        Sign in
                    </button>
                </form>

                <form x-show="authTab === 'register'" method="POST" action="{{ route('auth.register') }}" class="space-y-3" style="display:none">
                    @csrf

                    <div>
                        <label for="auth-register-name" class="mb-1.5 block text-xs font-medium text-body">Name</label>
                        <input id="auth-register-name" name="name" type="text" value="{{ old('name') }}" required
                               autocomplete="name" placeholder="Ada Lovelace" class="{{ $inputClass }}">
                        @error('name')
                            <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="auth-register-email" class="mb-1.5 block text-xs font-medium text-body">Email</label>
                        <input id="auth-register-email" name="email" type="email" value="{{ old('email') }}" required
                               autocomplete="email" placeholder="you@example.com" class="{{ $inputClass }}">
                    </div>

                    <div>
                        <label for="auth-register-password" class="mb-1.5 block text-xs font-medium text-body">Password</label>
                        <input id="auth-register-password" name="password" type="password" required
                               autocomplete="new-password" placeholder="At least 8 characters" class="{{ $inputClass }}">
                        @error('password')
                            <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="auth-register-password-confirmation" class="mb-1.5 block text-xs font-medium text-body">Confirm password</label>
                        <input id="auth-register-password-confirmation" name="password_confirmation" type="password" required
                               autocomplete="new-password" placeholder="Repeat your password" class="{{ $inputClass }}">
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                        Create account
                    </button>
                </form>

                <p class="mt-5 text-center text-xs text-faint">
                    Prefer the full page?
                    <a href="{{ route('login') }}" class="font-medium text-body underline transition hover:text-ink">Email sign-in page</a>
                </p>
            </div>
        </div>
    </div>
</div>
