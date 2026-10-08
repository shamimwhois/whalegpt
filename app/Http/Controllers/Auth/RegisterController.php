<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Account creation for the authentication modal.
 *
 * The account is signed in immediately rather than sending a verification
 * email, because this install has no mail transport configured by default and
 * an unusable half-finished account is worse than an unverified one.
 */
class RegisterController extends Controller
{
    /**
     * Create an account, then sign the new user straight in.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
        ]);

        event(new Registered($user));

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('chat.index'));
    }
}
