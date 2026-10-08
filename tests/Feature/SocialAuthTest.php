<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/**
 * Google and GitHub sign-in. The provider is faked at the Socialite facade, so
 * these exercise the callback's account matching without any network call.
 */
function fakeSocialiteUser(array $overrides = []): SocialiteUser
{
    return (new SocialiteUser)->map(array_merge([
        'id' => 'provider-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'avatar' => 'https://example.com/ada.png',
    ], $overrides))->setToken('test-token');
}

function fakeProvider(): Provider
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn(fakeSocialiteUser());

    return $provider;
}

test('an unsupported provider is a 404 rather than an arbitrary driver', function () {
    $this->get(route('auth.redirect', 'facebook'))->assertNotFound();
});

test('an unconfigured provider explains itself instead of redirecting', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->from('/')->get(route('auth.redirect', 'google'))
        ->assertRedirect('/')
        ->assertSessionHas('auth_error');
});

test('a configured provider redirects to its consent screen', function () {
    config([
        'services.google.client_id' => 'client-id',
        'services.google.client_secret' => 'client-secret',
        'services.google.redirect' => '/auth/google/callback',
    ]);

    $this->get(route('auth.redirect', 'google'))
        ->assertRedirectContains('accounts.google.com');
});

test('the callback registers a first-time visitor and links the provider', function () {
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn(fakeProvider());

    $this->get(route('auth.callback', 'google'))->assertRedirect(route('chat.index'));

    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    $this->assertDatabaseHas('social_accounts', [
        'provider' => 'google',
        'provider_id' => 'provider-123',
    ]);
});

test('the callback signs in an already linked account without duplicating it', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => 'provider-123']);

    Socialite::shouldReceive('driver')->once()->with('github')->andReturn(fakeProvider());

    $this->get(route('auth.callback', 'github'))->assertRedirect(route('chat.index'));

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

test('a provider that shares no email cannot complete the sign-in', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn(fakeSocialiteUser(['email' => null]));

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->from('/')->get(route('auth.callback', 'google'))
        ->assertRedirect('/')
        ->assertSessionHas('auth_error');

    $this->assertGuest();
});
