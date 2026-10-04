<?php

use App\Ai\ResponseDepth;
use App\Ai\ThinkingEffort;

test('the capability endpoint lists every mode and depth the server accepts', function () {
    $payload = $this->getJson(route('chat.capabilities'))->assertOk()->json();

    expect($payload['modes'])->toHaveCount(7)
        ->and(array_column($payload['modes'], 'id'))
        ->toBe(['chat', 'code', 'research', 'deep-search', 'study', 'security', 'art']);

    expect(array_column($payload['depths'], 'id'))
        ->toBe(['deep', 'fast', 'super']);

    expect(array_column($payload['depths'], 'label'))
        ->toBe(['Deep thinking', 'Fast', 'Super fast']);

    expect(array_column($payload['modes'], 'label'))
        ->toContain('Deep search', 'Security study');
});

test('the capability endpoint names every mentionable sub-agent', function () {
    $payload = $this->getJson(route('chat.capabilities'))->assertOk()->json();

    expect(array_column($payload['mentions'], 'id'))
        ->toContain(
            'coding_agent',
            'research_agent',
            'deep_search_agent',
            'study_agent',
            'ethical_hacking_agent',
            'art_agent',
        );
});

test('an unknown response depth is rejected before anything is sent', function () {
    $this->postJson(route('chat.send'), [
        'message' => 'hello',
        'depth' => 'extreme',
    ])->assertStatus(422)->assertJsonValidationErrors('depth');
});

test('an unknown mode is rejected before anything is sent', function () {
    $this->postJson(route('chat.send'), [
        'message' => 'hello',
        'mode' => 'baking',
    ])->assertStatus(422)->assertJsonValidationErrors('mode');
});

test('an agent mention outside the known set is rejected', function () {
    $this->postJson(route('chat.send'), [
        'message' => 'hello',
        'mentions' => ['not_an_agent'],
    ])->assertStatus(422)->assertJsonValidationErrors('mentions.0');
});

test('deeper depths allow more tool steps', function () {
    expect(ResponseDepth::DeepThinking->maxSteps())
        ->toBeGreaterThan(ResponseDepth::Fast->maxSteps())
        ->and(ResponseDepth::Fast->maxSteps())
        ->toBeGreaterThan(ResponseDepth::SuperFast->maxSteps());
});

test('the capabilities endpoint offers every thinking effort', function () {
    $payload = $this->getJson(route('chat.capabilities'))->assertOk()->json();

    expect(array_column($payload['efforts'], 'id'))
        ->toBe(['low', 'medium', 'high', 'xhigh'])
        ->and(array_column($payload['efforts'], 'label'))
        ->toBe(['Low', 'Medium', 'High', 'Extra high']);
});

test('an unknown thinking effort is rejected before anything is sent', function () {
    $this->postJson(route('chat.send'), [
        'message' => 'hello',
        'thinking' => 'enormous',
    ])->assertStatus(422)->assertJsonValidationErrors('thinking');
});

test('an effort only sends reasoning options a provider will accept', function () {
    expect(ThinkingEffort::ExtraHigh->providerOptions('openai', 'gpt-5'))
        ->toBe(['reasoning_effort' => 'high'])
        ->and(ThinkingEffort::ExtraHigh->providerOptions('openai', 'gpt-4o-mini'))
        ->toBe([])
        ->and(ThinkingEffort::ExtraHigh->providerOptions('anthropic', 'claude-sonnet-4-5'))
        ->toBe(['thinking' => ['type' => 'enabled', 'budget_tokens' => 48000]])
        ->and(ThinkingEffort::ExtraHigh->providerOptions('anthropic', 'claude-3-5-haiku-latest'))
        ->toBe([])
        ->and(ThinkingEffort::ExtraHigh->providerOptions('gemini', 'gemini-2.5-pro'))
        ->toBe(['thinkingConfig' => ['thinkingBudget' => 32768]])
        ->and(ThinkingEffort::Low->providerOptions('ollama', 'qwen3:4b'))
        ->toBe([]);
});

test('the model catalog tags every model with a selectable type', function () {
    $payload = $this->getJson(route('chat.models'))->assertOk()->json();

    $types = [];

    foreach ($payload['providers'] as $provider) {
        foreach ($provider['models'] as $model) {
            expect($model)->toHaveKeys(['type', 'reasoning'])
                ->and($model['type'])->toBeIn(['fast', 'reasoning']);

            $types[] = $model['type'];
        }
    }

    expect($types)->not->toBeEmpty();
});

test('the search toggles must be booleans', function () {
    $this->postJson(route('chat.send'), [
        'message' => 'hello',
        'web' => 'maybe',
    ])->assertStatus(422)->assertJsonValidationErrors('web');
});

test('an unknown depth falls back to the default rather than failing', function () {
    expect(ResponseDepth::fromRequest(null))->toBe(ResponseDepth::Fast)
        ->and(ResponseDepth::fromRequest('nonsense'))->toBe(ResponseDepth::Fast)
        ->and(ResponseDepth::fromRequest('super'))->toBe(ResponseDepth::SuperFast)
        ->and(ThinkingEffort::fromRequest(null))->toBe(ThinkingEffort::Medium)
        ->and(ThinkingEffort::fromRequest('nonsense'))->toBe(ThinkingEffort::Medium)
        ->and(ThinkingEffort::fromRequest('xhigh'))->toBe(ThinkingEffort::ExtraHigh);
});
