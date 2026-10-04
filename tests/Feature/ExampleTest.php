<?php

test('the front door redirects to the chat app', function () {
    $this->get('/')->assertRedirect('/ai/chat');
});

test('the chat app loads', function () {
    $this->get('/ai/chat')->assertOk();
});
