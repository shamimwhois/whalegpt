<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Signing in and out.
 *
 * There is no registration and no password reset: this is a single-user tool,
 * so an email identifies the one person allowed in and the account is created
 * on first successful sign-in. That is deliberately simple rather than secure
 * — turn WHALE_REQUIRE_AUTH on only when the app is not reachable from
 * anyone else's machine.
 */
class AuthController extends Controller
{
    /**
     * Show the sign-in form.
     */
    public function show(): View
    {
        return view('auth.login');
    }

    /**
     * Sign a user in, creating the account if this is their first visit.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::firstOrCreate(
            ['email' => strtolower($credentials['email'])],
            ['name' => str($credentials['email'])->before('@')->toString()],
        );

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('chat.index'));
    }

    /**
     * Sign the current user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
