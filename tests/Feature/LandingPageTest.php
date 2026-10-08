<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The landing page is the product's public face: it has to render for a guest
 * with no session, carry the whale branding, and offer the authentication modal
 * with both OAuth providers described honestly.
 */
test('the landing page renders for a guest', function () {
    $this->get('/')
        ->assertOk()
        ->assertViewIs('pages.landing')
        ->assertSee('Whale AI');
});

test('the landing page carries the authentication modal', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('Create account');
});

test('both oauth providers are offered in the modal', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Continue with Google')
        ->assertSee('Continue with GitHub');
});

test('an unconfigured provider is disabled rather than a broken redirect', function () {
    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('credentials are not set on this install', false);
});

test('the landing page links into the app for a signed-in visitor', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('Open app');
});
