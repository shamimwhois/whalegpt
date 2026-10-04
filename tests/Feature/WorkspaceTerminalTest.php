<?php

use Illuminate\Support\Str;

function terminalHeaders(): array
{
    return ['X-Whale-Workspace' => 'ws-'.Str::random(24)];
}

test('the terminal runs allowlisted commands', function () {
    $headers = terminalHeaders();

    $this->postJson(route('chat.workspace.terminal'), ['command' => 'mkdir docs'], $headers)
        ->assertOk()
        ->assertJsonPath('code', 0)
        ->assertJsonPath('cwd', '');

    $this->postJson(route('chat.workspace.terminal'), ['command' => 'touch docs/readme.md'], $headers)
        ->assertOk()
        ->assertJsonPath('code', 0);

    $listing = $this->postJson(route('chat.workspace.terminal'), ['command' => 'ls'], $headers)
        ->assertOk()
        ->json();

    expect($listing['output'])->toContain('docs/');

    $found = $this->postJson(route('chat.workspace.terminal'), ['command' => 'find docs'], $headers)
        ->assertOk()
        ->json();

    expect($found['output'])->toContain('docs/readme.md');
});

test('cd moves the working directory and pwd reports it', function () {
    $headers = terminalHeaders();

    $this->postJson(route('chat.workspace.terminal'), ['command' => 'mkdir src'], $headers)->assertOk();

    $cd = $this->postJson(route('chat.workspace.terminal'), ['command' => 'cd src'], $headers)
        ->assertOk()
        ->json();

    expect($cd['cwd'])->toBe('src');

    $pwd = $this->postJson(
        route('chat.workspace.terminal'),
        ['command' => 'pwd', 'cwd' => $cd['cwd']],
        $headers,
    )->assertOk()->json();

    expect($pwd['output'])->toBe('/src');
});

test('cat shows file contents and reports missing files', function () {
    $headers = terminalHeaders();

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'notes.md',
        'contents' => "alpha\nwhale line\ngamma",
    ], $headers)->assertOk();

    $cat = $this->postJson(route('chat.workspace.terminal'), ['command' => 'cat notes.md'], $headers)
        ->assertOk()
        ->json();

    expect($cat['code'])->toBe(0)
        ->and($cat['output'])->toContain('whale line');

    $missing = $this->postJson(route('chat.workspace.terminal'), ['command' => 'cat nope.md'], $headers)
        ->assertOk()
        ->json();

    expect($missing['code'])->toBe(1)
        ->and($missing['output'])->toContain('No such file');
});

test('grep reports matching lines with their file and line number', function () {
    $headers = terminalHeaders();

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'notes.md',
        'contents' => "alpha\nwhale line\ngamma",
    ], $headers)->assertOk();

    $result = $this->postJson(
        route('chat.workspace.terminal'),
        ['command' => 'grep whale notes.md'],
        $headers,
    )->assertOk()->json();

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('notes.md:2:');
});

test('an unknown command is refused with the allowlist', function () {
    $headers = terminalHeaders();

    $payload = $this->postJson(route('chat.workspace.terminal'), ['command' => 'sudo rm -rf /'], $headers)
        ->assertStatus(422)
        ->json();

    expect($payload['message'])
        ->toContain('Unknown command [sudo]')
        ->toContain('pwd, ls, cd, cat');
});

test('path traversal cannot escape the workspace through the terminal', function () {
    $headers = terminalHeaders();

    $result = $this->postJson(
        route('chat.workspace.terminal'),
        ['command' => 'cat ../../etc/passwd'],
        $headers,
    )->assertOk()->json();

    expect($result['code'])->toBe(1)
        ->and($result['output'])->not->toContain('root:');
});

test('rm refuses a directory without -r and removes it with -r', function () {
    $headers = terminalHeaders();

    $this->postJson(route('chat.workspace.terminal'), ['command' => 'mkdir scratch'], $headers)->assertOk();

    $refused = $this->postJson(route('chat.workspace.terminal'), ['command' => 'rm scratch'], $headers)
        ->assertOk()
        ->json();

    expect($refused['code'])->toBe(1)
        ->and($refused['output'])->toContain('use rm -r');

    $removed = $this->postJson(route('chat.workspace.terminal'), ['command' => 'rm -r scratch'], $headers)
        ->assertOk()
        ->json();

    expect($removed['code'])->toBe(0);

    $this->getJson(route('chat.workspace.index'), $headers)
        ->assertOk()
        ->assertJsonPath('directories', []);
});

test('the terminal is confined to its own workspace', function () {
    $owner = terminalHeaders();
    $intruder = terminalHeaders();

    $this->putJson(route('chat.workspace.store'), [
        'path' => 'secret.txt',
        'contents' => 'private payload',
    ], $owner)->assertOk();

    $seen = $this->postJson(route('chat.workspace.terminal'), ['command' => 'ls'], $intruder)
        ->assertOk()
        ->json();

    expect($seen['output'])->not->toContain('secret.txt');
});

test('the command is required', function () {
    $this->postJson(route('chat.workspace.terminal'), [], terminalHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('command');
});
