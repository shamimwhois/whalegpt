<?php

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function shareHeaders(): array
{
    return ['X-Whale-Workspace' => 'ws-'.Str::random(24)];
}

test('sharing a conversation issues a public read-only link', function () {
    $headers = shareHeaders();

    $conversation = Conversation::create([
        'session_id' => $headers['X-Whale-Workspace'],
        'title' => 'Shared thread',
        'mode' => 'chat',
    ]);

    $conversation->messages()->create(['role' => 'user', 'content' => 'Secret of the whale', 'position' => 1]);

    $payload = $this->postJson(route('chat.history.share', $conversation), [], $headers)
        ->assertOk()
        ->json();

    expect($payload['shared'])->toBeTrue()
        ->and(strlen($payload['token']))->toBe(40)
        ->and($payload['url'])->toBe(route('chat.shared', ['token' => $payload['token']]));

    // The link resolves for anyone, with no workspace header at all.
    $this->get($payload['url'])
        ->assertOk()
        ->assertSee('Shared thread')
        ->assertSee('Secret of the whale');
});

test('sharing is idempotent and can be revoked', function () {
    $headers = shareHeaders();

    $conversation = Conversation::create([
        'session_id' => $headers['X-Whale-Workspace'],
        'title' => 'Revoke me',
        'mode' => 'chat',
    ]);

    $first = $this->postJson(route('chat.history.share', $conversation), [], $headers)->json();
    $second = $this->postJson(route('chat.history.share', $conversation), [], $headers)->json();

    expect($second['token'])->toBe($first['token']);

    $this->deleteJson(route('chat.history.share.destroy', $conversation), [], $headers)->assertOk();

    $this->get(route('chat.shared', ['token' => $first['token']]))->assertNotFound();
});

test('another visitor cannot share a conversation they do not own', function () {
    $owner = shareHeaders();
    $intruder = shareHeaders();

    $conversation = Conversation::create([
        'session_id' => $owner['X-Whale-Workspace'],
        'title' => 'Private',
        'mode' => 'chat',
    ]);

    $this->postJson(route('chat.history.share', $conversation), [], $intruder)->assertForbidden();
    $this->deleteJson(route('chat.history.share.destroy', $conversation), [], $intruder)->assertForbidden();
});

test('an unknown share token 404s', function () {
    $this->get(route('chat.shared', ['token' => Str::random(40)]))->assertNotFound();
});

test('an unshared conversation has no public page', function () {
    $conversation = Conversation::create([
        'session_id' => 'ws-'.Str::random(24),
        'title' => 'Never shared',
        'mode' => 'chat',
    ]);

    $this->get('/ai/chat/shared/')->assertNotFound();
});
