<?php

use App\Models\Conversation;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function historyHeaders(?string $id = null): array
{
    return ['X-Whale-Workspace' => $id ?? ('ws-'.Str::random(24))];
}

test('a conversation can be created, renamed, pinned and deleted', function () {
    $headers = historyHeaders();

    $created = $this->postJson(route('chat.history.store'), ['title' => 'First chat'], $headers)
        ->assertCreated()
        ->json('conversation');

    $this->patchJson(route('chat.history.update', $created['id']), ['title' => 'Renamed', 'pinned' => true], $headers)
        ->assertOk()
        ->assertJsonPath('conversation.title', 'Renamed')
        ->assertJsonPath('conversation.pinned', true);

    $this->deleteJson(route('chat.history.destroy', $created['id']), [], $headers)->assertOk();

    $this->getJson(route('chat.history.index'), $headers)
        ->assertOk()
        ->assertJsonPath('conversations', []);
});

test('a project groups conversations and accepts a moved chat', function () {
    $headers = historyHeaders();

    $project = $this->postJson(route('chat.history.projects.store'), ['name' => 'Whale'], $headers)
        ->assertCreated()
        ->json('project');

    $conversation = $this->postJson(route('chat.history.store'), ['title' => 'Thread'], $headers)
        ->json('conversation');

    $this->patchJson(route('chat.history.update', $conversation['id']), ['project_id' => $project['id']], $headers)
        ->assertOk();

    $this->getJson(route('chat.history.index'), $headers)
        ->assertOk()
        ->assertJsonPath('projects.0.name', 'Whale')
        ->assertJsonPath('projects.0.conversations.0.title', 'Thread');
});

test('messages are appended and returned in order', function () {
    $headers = historyHeaders();
    $conversation = $this->postJson(route('chat.history.store'), [], $headers)->json('conversation');

    $this->postJson(route('chat.history.messages.append', $conversation['id']), [
        'role' => 'user',
        'content' => 'Hello',
    ], $headers)->assertCreated();

    $this->postJson(route('chat.history.messages.append', $conversation['id']), [
        'role' => 'assistant',
        'content' => 'Hi there',
    ], $headers)->assertCreated();

    $this->getJson(route('chat.history.messages', $conversation['id']), $headers)
        ->assertOk()
        ->assertJsonPath('messages.0.content', 'Hello')
        ->assertJsonPath('messages.1.content', 'Hi there');
});

test('another visitor cannot read or change a conversation', function () {
    $owner = historyHeaders();
    $intruder = historyHeaders();

    $conversation = $this->postJson(route('chat.history.store'), [], $owner)->json('conversation');

    $this->getJson(route('chat.history.messages', $conversation['id']), $intruder)->assertForbidden();
    $this->patchJson(route('chat.history.update', $conversation['id']), ['title' => 'Hijacked'], $intruder)->assertForbidden();
    $this->deleteJson(route('chat.history.destroy', $conversation['id']), [], $intruder)->assertForbidden();
});

test('a conversation exports as text, markdown, html and json', function () {
    $headers = historyHeaders();

    $conversation = Conversation::create([
        'session_id' => $headers['X-Whale-Workspace'],
        'title' => 'Export me',
        'mode' => 'chat',
    ]);

    $conversation->messages()->create(['role' => 'user', 'content' => 'Ping', 'position' => 1]);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Pong', 'position' => 2]);

    $text = $this->get(route('chat.history.export', ['conversation' => $conversation, 'format' => 'txt']), $headers);
    $text->assertOk()->assertHeader('content-type', 'text/plain; charset=UTF-8');
    expect($text->getContent())->toContain('Ping')->toContain('Pong');

    $markdown = $this->get(route('chat.history.export', ['conversation' => $conversation, 'format' => 'md']), $headers);
    expect($markdown->getContent())->toContain('# Export me');

    $html = $this->get(route('chat.history.export', ['conversation' => $conversation, 'format' => 'html']), $headers);
    expect($html->getContent())->toContain('<!doctype html>');

    $json = $this->get(route('chat.history.export', ['conversation' => $conversation, 'format' => 'json']), $headers);
    expect(json_decode($json->getContent(), true)['messages'])->toHaveCount(2);
});

test('an unknown export format is rejected', function () {
    $headers = historyHeaders();

    $conversation = Conversation::create([
        'session_id' => $headers['X-Whale-Workspace'],
        'title' => 'Export me',
    ]);

    $this->get(route('chat.history.export', ['conversation' => $conversation, 'format' => 'exe']), $headers)
        ->assertNotFound();
});

test('deleting a project keeps its conversations', function () {
    $headers = historyHeaders();

    $project = Project::create([
        'session_id' => $headers['X-Whale-Workspace'],
        'name' => 'Temp',
    ]);

    $conversation = Conversation::create([
        'session_id' => $headers['X-Whale-Workspace'],
        'project_id' => $project->id,
        'title' => 'Keep me',
    ]);

    $this->deleteJson(route('chat.history.projects.destroy', $project), [], $headers)->assertOk();

    expect(Conversation::find($conversation->id))->not->toBeNull()
        ->and(Conversation::find($conversation->id)->project_id)->toBeNull();
});
