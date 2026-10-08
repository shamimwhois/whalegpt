<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/**
 * Email and password sign in and registration — the half of the authentication
 * modal that works without any OAuth credentials.
 */
test('the modal can register an account and signs the user in', function () {
    $this->post(route('auth.register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect(route('chat.index'));

    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
});

test('registration rejects an email that already exists', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('auth.register'), [
        'name' => 'Someone',
        'email' => 'taken@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertSessionHasErrors('email');
});

test('registration requires the password to be confirmed', function () {
    $this->post(route('auth.register'), [
        'name' => 'Someone',
        'email' => 'someone@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'something-else',
    ])->assertSessionHasErrors('password');
});

test('the modal signs an existing user in with a correct password', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('auth.login'), [
        'email' => 'ada@example.com',
        'password' => 'password',
    ])->assertRedirect(route('chat.index'));

    $this->assertAuthenticatedAs($user);
});

test('a wrong password is rejected and leaves the guest signed out', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('auth.login'), [
        'email' => 'ada@example.com',
        'password' => 'not-the-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a signed-in user can sign out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
});
