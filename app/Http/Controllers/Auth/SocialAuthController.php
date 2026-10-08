<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Google and GitHub sign-in.
 *
 * The same route registers and signs in: a callback that finds no linked
 * account creates the user and links it, which is what makes the two provider
 * buttons a registration flow as well as a login one.
 */
class SocialAuthController extends Controller
{
    /**
     * The providers this install offers. Anything else 404s, so the route can
     * never be pointed at an arbitrary driver.
     *
     * @var list<string>
     */
    private const PROVIDERS = ['google', 'github'];

    /**
     * Hand the browser to the provider's consent screen.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureSupported($provider);

        if (! $this->isConfigured($provider)) {
            return back()->with('auth_error', ucfirst($provider).' sign-in is not configured on this install.');
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider's callback: sign in a linked account, or link and
     * create one for a first-time visitor.
     */
    public function callback(string $provider): RedirectResponse
    {
        $this->ensureSupported($provider);

        try {
            $remote = Socialite::driver($provider)->user();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('auth_error', 'That sign-in could not be completed. Please try again.');
        }

        $email = $remote->getEmail();

        // A provider that hides the email leaves nothing to key an account on.
        if ($email === null || $email === '') {
            return back()->with('auth_error', 'Your '.ucfirst($provider).' account did not share an email address.');
        }

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $remote->getId())
            ->first();

        if ($account?->user) {
            return $this->signIn($account->user);
        }

        // An existing email means this is the same person linking a second
        // provider, not a new account.
        $user = User::query()->where('email', strtolower($email))->first();

        $user ??= User::create([
            'name' => $remote->getName() ?: Str::before($email, '@'),
            'email' => strtolower($email),
            'email_verified_at' => now(),
        ]);

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $remote->getId(),
            'avatar_url' => $remote->getAvatar(),
        ]);

        return $this->signIn($user);
    }

    /**
     * Sign a user in and send them to the app.
     */
    private function signIn(User $user): RedirectResponse
    {
        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return redirect()->intended(route('chat.index'));
    }

    /**
     * Reject a provider that is not one of the offered two.
     */
    private function ensureSupported(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
    }

    /**
     * Whether the install has credentials for this provider.
     */
    private function isConfigured(string $provider): bool
    {
        return (string) config("services.{$provider}.client_id") !== ''
            && (string) config("services.{$provider}.client_secret") !== '';
    }
}
