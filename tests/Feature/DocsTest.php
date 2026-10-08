<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The documentation is public: it is what a visitor reads before signing in.
 * Every declared page must resolve, and an undeclared slug must 404 rather than
 * render an empty shell.
 */
test('the documentation index lists the guides', function () {
    $this->get(route('docs.index'))
        ->assertOk()
        ->assertViewIs('pages.docs.index')
        ->assertSee('Documentation');
});

test('every configured documentation page renders', function () {
    foreach (config('docs.pages') as $slug => $page) {
        $this->get(route('docs.show', $slug))
            ->assertOk()
            ->assertViewIs('pages.docs.guide')
            ->assertSee($page['title']);
    }
});

test('a page that is not published returns a 404', function () {
    $this->get(route('docs.show', 'no-such-page'))->assertNotFound();
});
