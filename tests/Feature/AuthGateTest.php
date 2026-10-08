<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// These tests touch the users table, which the wider suite deliberately leaves
// unmigrated.
uses(RefreshDatabase::class);

test('the chat page is reachable without authentication by default', function () {
    // Regression guard: auth was enforced with no sign-in route, so every
    // request to the app returned 403 with no way in.
    config(['whale.auth.required' => false]);

    $this->get(route('chat.index'))->assertOk();
});

test('the chat app and the sign-in page both render', function () {
    config(['whale.auth.required' => false]);

    $this->get(route('chat.index'))->assertOk();
    $this->get(route('login'))->assertOk();
});

test('a guest is sent to sign in when authentication is required', function () {
    config(['whale.auth.required' => true]);

    $this->get(route('chat.index'))->assertRedirect(route('login'));
});

test('a json caller is told it needs to authenticate', function () {
    config(['whale.auth.required' => true]);

    $this->getJson(route('chat.index'))->assertStatus(401);
});

test('signing in with an email creates the account and lets the user in', function () {
    config(['whale.auth.required' => true]);

    $this->post(route('login.store'), ['email' => 'dev@example.com'])
        ->assertRedirect(route('chat.index'));

    $this->assertAuthenticated();
    expect(User::where('email', 'dev@example.com')->exists())->toBeTrue();

    $this->get(route('chat.index'))->assertOk();
});

test('signing in requires a valid email', function () {
    $this->post(route('login.store'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('signing out ends the session', function () {
    $user = User::factory()->create();
    config(['whale.auth.required' => true]);

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});
