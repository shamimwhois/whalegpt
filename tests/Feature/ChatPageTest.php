<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The landing page at / introduces the product to anyone; /ai/chat is where a
 * visitor works once inside. Sign-in is optional by default and enforced only
 * when WHALE_REQUIRE_AUTH is on, which is the pair these cover.
 *
 * Sharing stays open on purpose and is covered by ShareConversationTest, which
 * asserts that a share link still resolves for an anonymous browser.
 */
test('the landing page is public, so a guest sees the product and not a refusal', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Whale');
});

test('a guest reaches the chat page while sign-in is optional', function () {
    config(['whale.auth.required' => false]);

    $this->get(route('chat.index'))->assertOk();
});

test('a guest is sent to sign in once authentication is required', function () {
    config(['whale.auth.required' => true]);

    $this->get(route('chat.index'))->assertRedirect(route('login'));
});

test('a signed-in visitor is shown the landing page with a link into the app', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('Open app');
});

test('an authenticated visitor is shown the chat page with a My chats nav link', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('chat.index'))
        ->assertOk()
        ->assertViewIs('chat.index')
        ->assertSee('My chats');
});

test('the chat page identifies the signed-in user rather than a placeholder', function () {
    $html = $this->actingAs(User::factory()->create(['name' => 'Grace Hopper']))
        ->get(route('chat.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Grace Hopper')
        ->and($html)->not->toContain('Alex Rivera');
});

test('the conversation is exposed as the main landmark', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get(route('chat.index'))
        ->assertOk()
        ->getContent();

    // Parsed rather than searched: the inline script carries a <main> inside a
    // template literal for the sandbox preview, and a substring count would
    // count that as a second landmark.
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    $main = $document->getElementsByTagName('main');

    expect($main->length)->toBe(1)
        ->and($main->item(0)?->getAttribute('aria-label'))->toBe('Conversation');
});

/**
 * The starter prompts used to be buttons under the greeting. They now rotate
 * in the composer's placeholder, so the empty state costs no clicks and a hint
 * cannot be sent by accident. These pin both halves of that.
 */
test('the starter prompts are not rendered as buttons', function () {
    $html = $this->get(route('chat.index'))->assertOk()->getContent();

    expect($html)->not->toContain('whale-chip justify-start', false)
        ->and($html)->not->toContain('x-on:click="draft =', false);
});

test('the composer placeholder rotates through the starter prompts', function () {
    $html = $this->get(route('chat.index'))->assertOk()->getContent();

    // The textarea is bound to the getter, and the getter is what picks the
    // prompt off the list. Asserting only that the words appear would pass even
    // with a hard-coded placeholder, so both ends are checked.
    expect($html)->toContain(':placeholder="placeholder"', false)
        ->and($html)->toContain('get placeholder()', false)
        ->and($html)->toContain('STARTER_PROMPTS[this.starterPromptIndex]', false)
        ->and($html)->toContain('Summarize this thread', false)
        ->and($html)->toContain('Write a migration', false)
        ->and($html)->toContain('Explain queues', false);
});

test('a draft suppresses the hint so it never covers what is being typed', function () {
    $html = $this->get(route('chat.index'))->assertOk()->getContent();

    // The rotation is a no-op while a draft exists, and the placeholder is
    // empty rather than a prompt sitting on top of real text.
    expect($html)->toContain('rotateStarterPrompt()', false)
        ->and($html)->toContain('if (this.draft.trim())', false);
});
