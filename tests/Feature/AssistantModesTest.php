<?php

use App\Http\Controllers\ChatController;
use App\Workspace\Workspace;

test('the assistant panel offers the planning and coding modes', function () {
    $modes = collect($this->getJson(route('chat.capabilities'))->json('modes'))->keyBy('id');

    expect($modes)->toHaveKeys(['ask', 'plan', 'agent', 'debug', 'orchestrate'])
        ->and($modes['plan']['description'])->toContain('Writes nothing')
        ->and($modes['orchestrate']['description'])->toContain('verify');
});

test('planning mode can read the workspace but cannot change it', function () {
    // The guarantee is structural: the write tools are simply absent, so a plan
    // cannot edit a file however the model is prompted.
    $method = new ReflectionMethod(ChatController::class, 'tools');
    $method->setAccessible(true);

    $controller = app(ChatController::class);
    $workspace = Workspace::forSession('');

    $names = function (string $mode) use ($method, $controller, $workspace): array {
        return array_map(
            fn (object $tool): string => class_basename($tool),
            $method->invoke($controller, $mode, $workspace, false, false),
        );
    };

    $planning = $names('plan');
    $coding = $names('agent');

    expect($planning)->toContain('ReadFileTool')
        ->and($planning)->not->toContain('WriteFileTool')
        ->and($planning)->not->toContain('DeleteFileTool')
        ->and($coding)->toContain('WriteFileTool')
        ->and($coding)->toContain('DeleteFileTool');
});

test('asking and debugging stay read-only while orchestration may write', function () {
    $method = new ReflectionMethod(ChatController::class, 'tools');
    $method->setAccessible(true);

    $controller = app(ChatController::class);
    $workspace = Workspace::forSession('');

    $names = fn (string $mode): array => array_map(
        fn (object $tool): string => class_basename($tool),
        $method->invoke($controller, $mode, $workspace, false, false),
    );

    foreach (['ask', 'debug'] as $mode) {
        expect($names($mode))->toContain('ReadFileTool')
            ->not->toContain('WriteFileTool')
            ->not->toContain('DeleteFileTool');
    }

    // Orchestrate is the one mode allowed to act, because its job is to finish
    // the work rather than describe it.
    expect($names('orchestrate'))->toContain('WriteFileTool')
        ->toContain('DeleteFileTool')
        ->toContain('CodingAgent');
});
