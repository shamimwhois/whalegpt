<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Support\SimplePdf;
use App\Workspace\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Exports a conversation as txt, markdown, HTML, JSON or PDF.
 *
 * The same underlying transcript is rendered five ways so a thread can be
 * pasted into notes, committed as documentation, printed or read as a real
 * PDF, or re-imported as structured data.
 */
class ConversationExportController extends Controller
{
    /**
     * Download a conversation in the requested format.
     */
    public function __invoke(Request $request, Conversation $conversation): Response
    {
        $this->authorize($request, $conversation);

        $format = Str::lower((string) $request->query('format', 'md'));

        abort_unless(in_array($format, ['txt', 'md', 'html', 'json', 'pdf'], true), 404);

        $messages = $conversation->messages()->get();
        $title = $conversation->title ?: 'Conversation';
        $slug = Str::slug($title) ?: 'conversation';

        [$body, $mime] = match ($format) {
            'txt' => [$this->asText($title, $messages), 'text/plain; charset=UTF-8'],
            'html' => [$this->asHtml($title, $messages), 'text/html; charset=UTF-8'],
            'json' => [$this->asJson($conversation, $messages), 'application/json'],
            'pdf' => [$this->asPdf($title, $messages), 'application/pdf'],
            default => [$this->asMarkdown($title, $messages), 'text/markdown; charset=UTF-8'],
        };

        return response($body, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => sprintf('attachment; filename="%s.%s"', $slug, $format),
        ]);
    }

    /**
     * @param  Collection<int, ChatMessage>  $messages
     */
    private function asText(string $title, $messages): string
    {
        $lines = [$title, str_repeat('=', mb_strlen($title)), ''];

        foreach ($messages as $message) {
            $lines[] = $this->speaker($message).':';
            $lines[] = (string) $message->content;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, ChatMessage>  $messages
     */
    private function asMarkdown(string $title, $messages): string
    {
        $lines = ['# '.$title, ''];

        foreach ($messages as $message) {
            $lines[] = '### '.$this->speaker($message);
            $lines[] = '';
            $lines[] = (string) $message->content;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, ChatMessage>  $messages
     */
    private function asHtml(string $title, $messages): string
    {
        $safeTitle = e($title);
        $rows = $messages->map(function (ChatMessage $message): string {
            $speaker = e($this->speaker($message));
            $content = e((string) $message->content);
            $role = e($message->role);

            return <<<HTML
                    <article class="turn {$role}">
                        <h2>{$speaker}</h2>
                        <pre>{$content}</pre>
                    </article>
            HTML;
        })->implode("\n");

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$safeTitle}</title>
            <style>
                :root { color-scheme: light dark; }
                body { margin: 0 auto; max-width: 46rem; padding: 2.5rem 1.25rem;
                       font: 16px/1.6 system-ui, sans-serif; }
                h1 { font-size: 1.6rem; margin: 0 0 1.5rem; }
                .turn { border-top: 1px solid color-mix(in oklab, currentColor 15%, transparent);
                        padding: 1.25rem 0; }
                .turn h2 { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em;
                           opacity: .6; margin: 0 0 .5rem; }
                .turn pre { margin: 0; white-space: pre-wrap; word-wrap: break-word;
                            font: inherit; }
                .turn.user h2 { color: #0e8f6e; }
                @media print { body { max-width: none; } }
            </style>
        </head>
        <body>
            <h1>{$safeTitle}</h1>
        {$rows}
        </body>
        </html>
        HTML;
    }

    /**
     * @param  Collection<int, ChatMessage>  $messages
     */
    private function asJson(Conversation $conversation, $messages): string
    {
        return json_encode([
            'title' => $conversation->title,
            'mode' => $conversation->mode,
            'exported_at' => now()->toIso8601String(),
            'messages' => $messages->map(fn (ChatMessage $message): array => [
                'role' => $message->role,
                'kind' => $message->kind,
                'content' => $message->content,
                'meta' => $message->meta,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * Render the transcript as a real PDF using the small dependency-free
     * writer, so exporting never needs a rendering library.
     *
     * @param  Collection<int, ChatMessage>  $messages
     */
    private function asPdf(string $title, $messages): string
    {
        $pdf = new SimplePdf;
        $pdf->add($title, bold: true, size: 16);
        $pdf->spacer(4);

        foreach ($messages as $message) {
            $pdf->add($this->speaker($message).':', bold: true, size: 11);
            $pdf->add((string) $message->content, size: 10.5);
            $pdf->spacer(6);
        }

        return $pdf->render();
    }

    private function speaker(ChatMessage $message): string
    {
        return $message->role === 'user' ? 'You' : 'Whale';
    }

    private function authorize(Request $request, Conversation $conversation): void
    {
        $owns = $conversation->session_id === WorkspaceContext::idFrom($request)
            || ($request->user() !== null && $conversation->user_id === $request->user()->id);

        abort_unless($owns, 403);
    }
}
