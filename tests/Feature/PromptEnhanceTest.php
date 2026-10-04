<?php

use Laravel\Ai\AnonymousAgent;

test('a draft prompt is rewritten', function () {
    AnonymousAgent::fake(['Rewrite the auth middleware to use a policy.']);

    $this->postJson(route('chat.enhance'), [
        'prompt' => 'fix auth',
    ])
        ->assertOk()
        ->assertJsonPath('original', 'fix auth')
        ->assertJsonPath('enhanced', 'Rewrite the auth middleware to use a policy.');

    AnonymousAgent::assertPrompted('fix auth');
});

test('an empty rewrite falls back to the original prompt', function () {
    AnonymousAgent::fake(['   ']);

    $this->postJson(route('chat.enhance'), ['prompt' => 'fix auth'])
        ->assertOk()
        ->assertJsonPath('enhanced', 'fix auth');
});

test('a prompt is required', function () {
    AnonymousAgent::fake()->preventStrayPrompts();

    $this->postJson(route('chat.enhance'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('prompt');

    AnonymousAgent::assertNeverPrompted();
});

test('an unknown style is rejected', function () {
    AnonymousAgent::fake()->preventStrayPrompts();

    $this->postJson(route('chat.enhance'), [
        'prompt' => 'fix auth',
        'style' => 'shouty',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('style');

    AnonymousAgent::assertNeverPrompted();
});

test('every documented style is accepted', function () {
    AnonymousAgent::fake(['A rewritten prompt.']);

    foreach (['clearer', 'shorter', 'detailed', 'technical', 'creative'] as $style) {
        $this->postJson(route('chat.enhance'), [
            'prompt' => 'fix auth',
            'style' => $style,
        ])->assertOk();
    }
});
