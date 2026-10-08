<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The public front door is the landing page now; the chat app lives behind it
 * at /ai/chat. These are the two top-level routes a browser is expected to
 * reach.
 */
test('the landing page renders at the front door', function () {
    $this->get('/')
        ->assertOk()
        ->assertViewIs('pages.landing');
});

test('the chat app loads for a signed-in visitor', function () {
    $this->actingAs(User::factory()->create())
        ->get('/ai/chat')
        ->assertOk();
});
