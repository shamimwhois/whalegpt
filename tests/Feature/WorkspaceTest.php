<?php

use App\Workspace\Workspace;
use Illuminate\Support\Str;

function workspaceHeaders(string $id): array
{
    return ['X-Whale-Workspace' => $id];
}

/**
 * A fresh workspace id, so tests never collide in storage.
 */
function freshWorkspaceId(): string
{
    return 'ws-'.Str::random(24);
}

test('a file can be written, read and listed', function () {
    $id = freshWorkspaceId();

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'src/index.html',
        'contents' => '<h1>Hello</h1>',
    ], workspaceHeaders($id))->assertOk();

    $this->getJson(route('chat.workspace.show', ['path' => 'src/index.html']), workspaceHeaders($id))
        ->assertOk()
        ->assertJsonPath('contents', '<h1>Hello</h1>');

    $this->getJson(route('chat.workspace.index'), workspaceHeaders($id))
        ->assertOk()
        ->assertJsonPath('files.0.path', 'src/index.html');
});

test('a file can be moved and deleted', function () {
    $id = freshWorkspaceId();
    $headers = workspaceHeaders($id);

    $this->putJson(route('chat.workspace.store'), ['path' => 'a.txt', 'contents' => 'x'], $headers)->assertOk();

    $this->postJson(route('chat.workspace.move'), ['from' => 'a.txt', 'to' => 'nested/a.txt'], $headers)
        ->assertOk()
        ->assertJsonPath('files.0.path', 'nested/a.txt');

    $this->deleteJson(route('chat.workspace.destroy', ['path' => 'nested/a.txt']), [], $headers)
        ->assertOk()
        ->assertJsonPath('files', []);
});

test('path traversal is rejected', function () {
    $id = freshWorkspaceId();

    $this->putJson(route('chat.workspace.store'), [
        'path' => '../../etc/passwd',
        'contents' => 'nope',
    ], workspaceHeaders($id))->assertStatus(422);
});

test('absolute paths are rejected', function () {
    $id = freshWorkspaceId();

    $this->putJson(route('chat.workspace.store'), [
        'path' => '/etc/passwd',
        'contents' => 'nope',
    ], workspaceHeaders($id))->assertStatus(422);
});

test('one workspace cannot read another workspaces files', function () {
    $owner = freshWorkspaceId();
    $intruder = freshWorkspaceId();

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'secret.txt',
        'contents' => 'private',
    ], workspaceHeaders($owner))->assertOk();

    $this->getJson(route('chat.workspace.show', ['path' => 'secret.txt']), workspaceHeaders($intruder))
        ->assertNotFound();
});

test('the workspace service confines writes to its root', function () {
    $workspace = Workspace::forSession(freshWorkspaceId());

    expect(fn () => $workspace->write('../escape.txt', 'x'))
        ->toThrow(RuntimeException::class);
});

test('search finds a match with its line number', function () {
    $id = freshWorkspaceId();
    $headers = workspaceHeaders($id);

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'notes.md',
        'contents' => "first line\nfind me here\nlast line",
    ], $headers)->assertOk();

    $workspace = Workspace::forSession($id);

    expect($workspace->search('find me'))
        ->toHaveCount(1)
        ->and($workspace->search('find me')[0]['line'])->toBe(2);
});

test('an empty directory appears in the tree', function () {
    $id = freshWorkspaceId();
    $headers = workspaceHeaders($id);

    $this->postJson(route('chat.workspace.mkdir'), ['path' => 'components'], $headers)
        ->assertOk()
        ->assertJsonPath('directories.0', 'components')
        ->assertJsonPath('files', []);
});

test('a nested directory is rejected when it escapes the workspace', function () {
    $id = freshWorkspaceId();

    $this->postJson(route('chat.workspace.mkdir'), ['path' => '../../escape'], workspaceHeaders($id))
        ->assertStatus(422);
});

test('the tree reports the directory a file lives in', function () {
    $id = freshWorkspaceId();
    $headers = workspaceHeaders($id);

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'src/app/main.js',
        'contents' => 'x',
    ], $headers)->assertOk();

    $this->getJson(route('chat.workspace.index'), $headers)
        ->assertOk()
        ->assertJsonPath('directories', ['src', 'src/app']);
});

test('the search endpoint returns matches with a line number', function () {
    $id = freshWorkspaceId();
    $headers = workspaceHeaders($id);

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'notes.md',
        'contents' => "first line\nfind me here\nlast line",
    ], $headers)->assertOk();

    $payload = $this->getJson(route('chat.workspace.search', ['q' => 'find me']), $headers)
        ->assertOk()
        ->json();

    expect($payload['query'])->toBe('find me')
        ->and($payload['matches'])->toHaveCount(1)
        ->and($payload['matches'][0]['path'])->toBe('notes.md')
        ->and($payload['matches'][0]['line'])->toBe(2)
        ->and($payload['matches'][0]['excerpt'])->toContain('find me here');
});

test('a search with no hits returns an empty list rather than an error', function () {
    $id = freshWorkspaceId();

    $payload = $this->getJson(route('chat.workspace.search', ['q' => 'nothing']), workspaceHeaders($id))
        ->assertOk()
        ->json();

    expect($payload['matches'])->toBeEmpty();
});

test('search never sees another workspaces files', function () {
    $owner = freshWorkspaceId();
    $intruder = freshWorkspaceId();

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'secret.txt',
        'contents' => 'private payload',
    ], workspaceHeaders($owner))->assertOk();

    $payload = $this->getJson(
        route('chat.workspace.search', ['q' => 'private']),
        workspaceHeaders($intruder),
    )->assertOk()->json();

    expect($payload['matches'])->toBeEmpty();
});

test('the search query is required', function () {
    $this->getJson(route('chat.workspace.search'), workspaceHeaders(freshWorkspaceId()))
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

test('the workspace page renders', function () {
    $this->get(route('chat.workspace'))->assertOk()->assertViewIs('chat.workspace');
});

test('the studio page renders', function () {
    $this->get(route('chat.studio'))->assertOk()->assertViewIs('chat.studio');
});
