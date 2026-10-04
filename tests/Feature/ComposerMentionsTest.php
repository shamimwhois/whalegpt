<?php

use Illuminate\Support\Str;

/**
 * A fresh workspace id, so tests never collide in storage.
 */
function mentionWorkspaceId(): string
{
    return 'ws-'.Str::random(24);
}

test('the mention endpoint lists workspace files for the composer', function () {
    $id = mentionWorkspaceId();
    $headers = ['X-Whale-Workspace' => $id];

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'src/app.js',
        'contents' => 'console.log(1);',
    ], $headers)->assertOk();

    $payload = $this->getJson(route('chat.workspace.mentions'), $headers)
        ->assertOk()
        ->json();

    expect($payload['files'])->toHaveCount(1)
        ->and($payload['files'][0]['id'])->toBe('src/app.js')
        ->and($payload['files'][0]['label'])->toBe('app.js')
        ->and($payload['files'][0]['path'])->toBe('src/app.js');
});

test('the mention endpoint never leaks a file from another workspace', function () {
    $owner = mentionWorkspaceId();
    $intruder = mentionWorkspaceId();

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'private.txt',
        'contents' => 'secret',
    ], ['X-Whale-Workspace' => $owner])->assertOk();

    $payload = $this->getJson(route('chat.workspace.mentions'), ['X-Whale-Workspace' => $intruder])
        ->assertOk()
        ->json();

    expect($payload['files'])->toBeEmpty();
});
