<?php

use App\Ai\Agents\ChatAgent;
use App\Ai\ResponseDepth;
use App\Ai\ResponseLength;
use App\Ai\ThinkingEffort;

test('the capability endpoint offers every response length', function () {
    $payload = $this->getJson(route('chat.capabilities'))->assertOk()->json();

    expect(array_column($payload['lengths'], 'id'))
        ->toBe(['auto', 'short', 'medium', 'long'])
        ->and(array_column($payload['lengths'], 'label'))
        ->toBe(['Auto', 'Short', 'Medium', 'Long']);
});

test('an unknown response length is rejected before anything is sent', function () {
    $this->postJson(route('chat.send'), [
        'message' => 'hello',
        'length' => 'enormous',
    ])->assertStatus(422)->assertJsonValidationErrors('length');
});

test('an unknown length falls back to the default rather than failing', function () {
    expect(ResponseLength::fromRequest(null))->toBe(ResponseLength::Auto)
        ->and(ResponseLength::fromRequest('nonsense'))->toBe(ResponseLength::Auto)
        ->and(ResponseLength::fromRequest('short'))->toBe(ResponseLength::Short);
});

test('auto sends no output cap so the provider default stands', function () {
    expect(ResponseLength::Auto->cap())->toBeNull()
        // Even when thinking is expensive, Auto stays uncapped: the user asked
        // for the provider's own behaviour.
        ->and(ResponseLength::Auto->cap(48_000))->toBeNull();
});

test('a length requests progressively larger output ceilings', function () {
    expect(ResponseLength::Short->cap())
        ->toBeLessThan(ResponseLength::Medium->cap())
        ->and(ResponseLength::Medium->cap())
        ->toBeLessThan(ResponseLength::Long->cap());
});

test('a length never caps below the thinking budget it is sent with', function () {
    // Anthropic rejects a max_tokens at or below its extended-thinking budget,
    // and an OpenAI reasoning model draws internal tokens from the same
    // allowance, so a short request must still clear the budget.
    expect(ResponseLength::Short->cap(48_000))
        ->toBeGreaterThan(ThinkingEffort::ExtraHigh->anthropicBudget())
        ->and(ResponseLength::Short->cap(32_768))
        ->toBeGreaterThan(ThinkingEffort::ExtraHigh->geminiBudget());
});

test('the agent reports the ceiling its length and effort imply', function () {
    $agent = fn (ResponseLength $length, ThinkingEffort $effort): ChatAgent => new ChatAgent(
        instructions: 'test',
        messages: [],
        tools: [],
        depth: ResponseDepth::Fast,
        effort: $effort,
        length: $length,
    );

    expect($agent(ResponseLength::Auto, ThinkingEffort::Low)->maxTokens())->toBeNull()
        ->and($agent(ResponseLength::Short, ThinkingEffort::Low)->maxTokens())->toBe(4_000)
        ->and($agent(ResponseLength::Medium, ThinkingEffort::Low)->maxTokens())->toBe(16_000)
        // A cheap budget cannot lift a generous request.
        ->and($agent(ResponseLength::Long, ThinkingEffort::Low)->maxTokens())->toBe(64_000)
        // An expensive one does lift a stingy request clear of itself.
        ->and($agent(ResponseLength::Short, ThinkingEffort::ExtraHigh)->maxTokens())
        ->toBeGreaterThan(48_000);
});

test('the default agent reports no cap', function () {
    // A length is optional, so an agent built without one must behave exactly
    // as it did before the setting existed.
    $agent = new ChatAgent(
        instructions: 'test',
        messages: [],
        tools: [],
        depth: ResponseDepth::Fast,
        effort: ThinkingEffort::Medium,
    );

    expect($agent->maxTokens())->toBeNull()
        ->and($agent->maxSteps())->toBe(ResponseDepth::Fast->maxSteps());
});
