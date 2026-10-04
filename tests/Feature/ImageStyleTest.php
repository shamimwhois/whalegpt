<?php

use App\Ai\ImageStyle;

test('the capability endpoint offers every image style with "any" first', function () {
    $styles = $this->getJson(route('chat.capabilities'))->assertOk()->json()['styles'];

    expect($styles[0]['id'])->toBe('any')
        ->and(array_column($styles, 'id'))
        ->toContain('realistic', 'cartoon', 'logo', 'animation', 'illustration', 'pixel', 'line-art', 'watercolor', '3d', 'neon');

    // Every style has to describe itself, or the chip is unreadable.
    foreach ($styles as $style) {
        expect($style['label'])->not->toBeEmpty()
            ->and($style['description'])->not->toBeEmpty()
            ->and($style['family'])->not->toBeEmpty();
    }
});

test('a style is appended to the prompt as a direction', function () {
    $styled = ImageStyle::apply('a whale', 'cartoon');

    expect($styled)->toContain('a whale')
        ->toContain('Style:')
        ->toContain('Bold cartoon style');
});

test('no style and the "any" style both leave the prompt untouched', function () {
    expect(ImageStyle::apply('a whale', null))->toBe('a whale')
        ->and(ImageStyle::apply('a whale', 'any'))->toBe('a whale');
});

test('an unknown style is rejected before a provider is ever called', function () {
    $this->postJson(route('chat.image'), [
        'prompt' => 'a whale',
        'style' => 'hologram',
    ])->assertStatus(422)->assertJsonValidationErrors('style');
});

test('an unknown style is rejected on image editing too', function () {
    $this->postJson(route('chat.image.edit'), [
        'prompt' => 'a whale',
        'style' => 'hologram',
    ])->assertStatus(422)->assertJsonValidationErrors('style');
});

test('an unknown style is rejected on storyboard generation', function () {
    $this->postJson(route('chat.video'), [
        'prompt' => 'a whale',
        'style' => 'hologram',
    ])->assertStatus(422)->assertJsonValidationErrors('style');
});

test('an unknown style is rejected on vector generation', function () {
    $this->postJson(route('chat.vector'), [
        'prompt' => 'a whale',
        'style' => 'hologram',
    ])->assertStatus(422)->assertJsonValidationErrors('style');
});

test('every catalogued style and edit operation applies a directive', function () {
    foreach (array_column(ImageStyle::toArray(), 'id') as $id) {
        if ($id === 'any') {
            continue;
        }

        $applied = ImageStyle::apply('a whale', $id);

        expect($applied)
            ->toContain('a whale')
            ->toContain(': ')
            ->not->toBe('a whale');
    }
});

test('edit operations are grouped separately from visual styles', function () {
    expect(ImageStyle::Upscale->isEdit())->toBeTrue()
        ->and(ImageStyle::Inpaint->isEdit())->toBeTrue()
        ->and(ImageStyle::Cartoon->isEdit())->toBeFalse()
        ->and(ImageStyle::Realistic->family())->toBe('Photo');

    $families = array_column(ImageStyle::toArray(), 'family');

    expect($families)->toContain('Edit', 'Photo', 'Drawn', 'Design', 'Art');
});

test('edit operations append an Edit direction rather than a style', function () {
    expect(ImageStyle::apply('a photo', 'upscale'))
        ->toContain('a photo')
        ->toContain('— Edit:');
});
