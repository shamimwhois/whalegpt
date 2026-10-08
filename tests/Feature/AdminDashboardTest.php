<?php

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The admin panel is gated by an email allowlist rather than a role. These
 * cover the three answers: a guest is sent to sign in, a signed-in non-admin is
 * forbidden, and an allowlisted administrator sees live figures.
 */
test('a guest is sent to sign in rather than shown the admin', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('a signed-in user who is not on the allowlist is forbidden', function () {
    config(['admin.emails' => ['boss@example.com']]);

    $this->actingAs(User::factory()->create(['email' => 'nobody@example.com']))
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('an allowlisted administrator sees the dashboard', function () {
    config(['admin.emails' => ['boss@example.com']]);

    $this->actingAs(User::factory()->create(['email' => 'boss@example.com']))
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewIs('pages.admin.dashboard')
        ->assertSee('Overview');
});

test('the allowlist is matched case-insensitively', function () {
    config(['admin.emails' => ['boss@example.com']]);

    $this->actingAs(User::factory()->create(['email' => 'BOSS@example.com']))
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('the dashboard reports real counts from the application tables', function () {
    config(['admin.emails' => ['boss@example.com']]);

    $admin = User::factory()->create(['email' => 'boss@example.com']);
    $author = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $author->id,
        'title' => 'Release notes',
    ]);

    ChatMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'kind' => 'text',
        'content' => 'Hello',
        'position' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('stats', function (array $stats): bool {
            return $stats['users'] === 2
                && $stats['conversations'] === 1
                && $stats['messages'] === 1;
        });
});
