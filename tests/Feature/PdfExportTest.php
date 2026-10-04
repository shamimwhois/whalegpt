<?php

use App\Models\Conversation;
use App\Support\SimplePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('a conversation exports as a real pdf', function () {
    $headers = ['X-Whale-Workspace' => 'ws-'.Str::random(24)];

    $conversation = Conversation::create([
        'session_id' => $headers['X-Whale-Workspace'],
        'title' => 'Pdf thread',
        'mode' => 'chat',
    ]);

    $conversation->messages()->create(['role' => 'user', 'content' => 'Hello with (parens)', 'position' => 1]);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Hi there', 'position' => 2]);

    $response = $this->get(
        route('chat.history.export', ['conversation' => $conversation, 'format' => 'pdf']),
        $headers,
    );

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    $pdf = $response->getContent();

    expect(substr($pdf, 0, 8))->toBe('%PDF-1.4')
        ->and(str_contains(substr($pdf, -16), '%%EOF'))->toBeTrue()
        ->and($pdf)->toContain('Pdf thread')
        ->and($pdf)->toContain('Hello');
});

test('the pdf writer paginates a long transcript', function () {
    $pdf = new SimplePdf;
    $pdf->add('Long thread', bold: true, size: 16);

    for ($line = 0; $line < 300; $line++) {
        $pdf->add("Transcript line {$line}, long enough to wrap across the page width when repeated.");
    }

    $rendered = $pdf->render();

    expect(substr($rendered, 0, 8))->toBe('%PDF-1.4')
        ->and(substr_count($rendered, '/Type /Page '))->toBeGreaterThan(1)
        ->and(str_contains(substr($rendered, -16), '%%EOF'))->toBeTrue()
        ->and($rendered)->toContain('Transcript line 299');
});

test('pdf text escapes parentheses and backslashes', function () {
    $input = 'a (nested) value with C:'.chr(92).'path';

    $pdf = new SimplePdf;
    $pdf->add($input);

    $rendered = $pdf->render();

    // Raw, unescaped parens or single backslashes inside the content stream
    // would corrupt the file, so the writer must emit the escaped forms.
    expect($rendered)->toContain('\(nested\)')
        ->and($rendered)->toContain('C:'.str_repeat(chr(92), 2).'path');
});
