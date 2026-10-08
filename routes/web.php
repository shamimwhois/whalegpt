<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\LandingController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

// The public landing page. A signed-in visitor is offered a link into the app
// rather than a sign-in call to action; a guest can open the authentication
// modal from the navigation. The chat app itself lives at /ai/chat.
Route::get('/', LandingController::class)->name('home');

/*
|--------------------------------------------------------------------------
| Documentation
|--------------------------------------------------------------------------
|
| The API guides are public: they are what a visitor reads before deciding to
| sign in. An unknown slug 404s rather than rendering an empty page.
|
*/

Route::get('docs', [DocsController::class, 'index'])->name('docs.index');

Route::get('docs/{page}', [DocsController::class, 'show'])->name('docs.show');

/*
|--------------------------------------------------------------------------
| Sign in
|--------------------------------------------------------------------------
|
| These routes exist so that turning on WHALE_REQUIRE_AUTH leads somewhere.
| Without them the middleware could only refuse every request, which is what
| locked the application out in the first place.
|
*/

Route::get('login', [AuthController::class, 'show'])
    ->name('login');

Route::post('login', [AuthController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('login.store');

Route::post('logout', [AuthController::class, 'destroy'])
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authentication modal
|--------------------------------------------------------------------------
|
| The modal on the landing page posts here. Email and password sign in and
| register have their own controllers; Google and GitHub share the
| redirect/callback pair, where a first callback registers the account.
|
*/

Route::post('auth/login', [LoginController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('auth.login');

Route::post('auth/register', [RegisterController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('auth.register');

Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->name('auth.redirect');

Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->name('auth.callback');

/*
|--------------------------------------------------------------------------
| Pricing and billing
|--------------------------------------------------------------------------
|
| The pricing page is public: a visitor has to be able to read what a plan costs
| before deciding whether to sign in. Everything that moves money needs an
| account, because a payment has to belong to somebody.
|
*/

Route::get('pricing', [BillingController::class, 'index'])->name('pricing');

Route::middleware('auth')->group(function () {
    Route::get('billing', [BillingController::class, 'status'])->name('billing.status');
    Route::post('billing/checkout', [BillingController::class, 'checkout'])
        ->middleware('throttle:10,1')
        ->name('billing.checkout');
    Route::post('billing/portal', [BillingController::class, 'portal'])
        ->middleware('throttle:10,1')
        ->name('billing.portal');
    Route::get('billing/success', [BillingController::class, 'success'])->name('billing.success');
});

// The provider posts here from outside the app, so it carries no session and
// cannot be CSRF-protected; the signature check inside the controller is what
// makes the body trustworthy.
Route::post('billing/webhook', [BillingController::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('billing.webhook');

// Staff-only surface. An admin passes this because Admin outranks Staff.
Route::middleware([EnsureRole::class.':staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::view('billing', 'staff.billing')->name('billing');
    });
