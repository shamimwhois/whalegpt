<?php

use App\Ai\Agents\ChatAgent;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

/**
 * Reassemble the deltas of a given stream part type the way the chat UI does.
 */
function streamedDeltas(string $content, string $type): string
{
    return collect(preg_split('/\n\n/', $content))
        ->filter(fn (string $frame): bool => str_starts_with($frame, 'data: {'))
        ->map(fn (string $frame): mixed => json_decode(substr($frame, 5), true))
        ->filter()
        ->where('type', $type)
        ->pluck('delta')
        ->implode('');
}

test('chat screen is rendered from the named route', function () {
    $this->get(route('chat.index'))
        ->assertOk()
        ->assertViewIs('chat.index');
});

test('a submitted message is streamed back as server sent events', function () {
    ChatAgent::fake(['The response from the assistant.']);

    $response = $this->post(route('chat.send'), [
        'message' => 'How do I create a migration?',
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/event-stream');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('"type":"start"')
        ->toContain('"type":"text-delta"')
        ->toContain('"type":"finish"')
        ->toContain('data: [DONE]');

    expect(streamedDeltas($content, 'text-delta'))->toBe('The response from the assistant.');

    ChatAgent::assertPrompted('How do I create a migration?');
});

test('reasoning is streamed as its own parts', function () {
    ChatAgent::fake([
        AgentResponse::fakeWithReasoning('Let me consider the options.', 'The answer.'),
    ]);

    $content = $this->post(route('chat.send'), ['message' => 'Hi'])->streamedContent();

    expect($content)
        ->toContain('"type":"reasoning-start"')
        ->toContain('"type":"reasoning-delta"')
        ->toContain('"type":"reasoning-end"');

    expect(streamedDeltas($content, 'reasoning-delta'))
        ->toBe('Let me consider the options.')
        ->and(streamedDeltas($content, 'text-delta'))
        ->toBe('The answer.');
});

test('message input is required', function () {
    ChatAgent::fake()->preventStrayPrompts();

    $this->postJson(route('chat.send'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    ChatAgent::assertNeverPrompted();
});

test('message input is limited to the composer maxlength', function () {
    ChatAgent::fake()->preventStrayPrompts();

    // The composer's textarea carries maxlength="20000"; the server has to
    // reject the same length or a long paste would be accepted then refused.
    $limit = 20000;

    $this->postJson(route('chat.send'), [
        'message' => str_repeat('a', $limit + 1),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    ChatAgent::assertNeverPrompted();
});

test('a long prompt is accepted rather than silently truncated', function () {
    ChatAgent::fake(['Here is a long answer.']);

    $this->postJson(route('chat.send'), [
        'message' => str_repeat('a', 19999),
    ])->assertOk();

    ChatAgent::assertPrompted(fn (AgentPrompt $prompt): bool => strlen($prompt->prompt) === 19999);
});
