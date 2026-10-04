<?php

namespace App\Http\Controllers;

use App\Workspace\Terminal;
use App\Workspace\Workspace;
use App\Workspace\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The browser IDE's backend: list, read, write, rename, delete and preview the
 * files in a session's sandboxed workspace.
 *
 * Every request resolves its own workspace from a validated identifier, so the
 * boundary enforced by App\Workspace is the only way in or out.
 */
class WorkspaceController extends Controller
{
    /**
     * The largest upload the IDE accepts, in kilobytes.
     */
    private const MAX_UPLOAD_KB = 5120;

    /**
     * List every file in the workspace.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);

        return response()->json($this->payload($workspace));
    }

    /**
     * Full-text search across every text file in the workspace.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:200'],
        ]);

        return response()->json([
            'query' => $validated['q'],
            'matches' => $this->workspace($request)->search($validated['q']),
        ]);
    }

    /**
     * Create a directory in the workspace, so the tree can hold a folder
     * before anything has been written into it.
     */
    public function mkdir(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $workspace = $this->workspace($request);

        try {
            $workspace->makeDirectory($validated['path']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->payload($workspace, ['path' => $validated['path']]));
    }

    /**
     * The files a user can @-mention, as a flat, searchable list.
     *
     * The composer asks for this once and filters it in the browser, so a
     * mention never costs a round-trip per keystroke.
     */
    public function mentions(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);

        $files = collect($workspace->files())
            ->map(fn (array $file): array => [
                'id' => $file['path'],
                'label' => basename($file['path']),
                'path' => $file['path'],
                'size' => $file['size'] ?? null,
            ])
            ->values()
            ->all();

        return response()->json(['files' => $files]);
    }

    /**
     * Read a single file's contents.
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $workspace = $this->workspace($request);

        try {
            $contents = $workspace->read($validated['path']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'path' => $validated['path'],
            'contents' => $contents,
            'binary' => ! $workspace->isText($validated['path']),
        ]);
    }

    /**
     * Create or replace a file.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'contents' => ['present', 'string', 'max:'.Workspace::MAX_FILE_BYTES],
        ]);

        $workspace = $this->workspace($request);

        try {
            $workspace->write($validated['path'], $validated['contents']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->payload($workspace, [
            'path' => $validated['path'],
        ]));
    }

    /**
     * Upload one or more files into the workspace.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.self::MAX_UPLOAD_KB],
            'directory' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $workspace = $this->workspace($request);
        $directory = $request->input('directory');
        $stored = [];

        foreach ($request->file('files', []) as $file) {
            try {
                $stored[] = $workspace->storeUpload($file, $directory);
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json($this->payload($workspace, ['stored' => $stored]));
    }

    /**
     * Rename or move a file (used by drag-and-drop in the file tree).
     */
    public function move(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
        ]);

        $workspace = $this->workspace($request);

        try {
            $workspace->move($validated['from'], $validated['to']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->payload($workspace));
    }

    /**
     * Delete a file.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $workspace = $this->workspace($request);
        $workspace->delete($validated['path']);

        return response()->json($this->payload($workspace));
    }

    /**
     * Serve a file inline so the IDE's preview pane can render it.
     *
     * The response is streamed from the confined path, never a user-supplied
     * absolute path, so it cannot be used to read outside the workspace.
     */
    public function preview(Request $request): Response|BinaryFileResponse|StreamedResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $workspace = $this->workspace($request);

        try {
            $absolute = $workspace->resolve($validated['path']);
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        if (! is_file($absolute)) {
            abort(404);
        }

        $extension = Str::lower(pathinfo($absolute, PATHINFO_EXTENSION));

        $contentType = match ($extension) {
            'html', 'htm' => 'text/html',
            'svg' => 'image/svg+xml',
            'css' => 'text/css',
            'js', 'mjs' => 'text/javascript',
            'json' => 'application/json',
            'md', 'markdown', 'txt' => 'text/plain; charset=UTF-8',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        return response()->file($absolute, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Download the whole workspace as a zip archive (the "publish/export" path).
     */
    public function export(Request $request): StreamedResponse
    {
        $workspace = $this->workspace($request);
        $files = $workspace->files();
        $name = 'whale-workspace-'.now()->format('Ymd-His').'.zip';

        return response()->streamDownload(function () use ($workspace, $files): void {
            $temporary = tempnam(sys_get_temp_dir(), 'whale');

            $zip = new \ZipArchive;

            if ($zip->open($temporary, \ZipArchive::OVERWRITE) !== true) {
                return;
            }

            foreach ($files as $file) {
                try {
                    $zip->addFromString($file['path'], $workspace->read($file['path']));
                } catch (Throwable) {
                    // A file that vanished mid-export is simply skipped.
                }
            }

            $zip->close();

            readfile($temporary);
            @unlink($temporary);
        }, $name, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Run one allowlisted terminal command inside the workspace.
     *
     * The interpreter never spawns a process: these routes carry no
     * authentication, so a real shell would be remote code execution for
     * anyone who found the endpoint. See App\Workspace\Terminal.
     */
    public function terminal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'max:200'],
            'cwd' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $workspace = $this->workspace($request);
        $cwd = (string) ($validated['cwd'] ?? '');

        try {
            $result = (new Terminal($workspace))->run($validated['command'], $cwd);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * Resolve the workspace for this request.
     */
    private function workspace(Request $request): Workspace
    {
        return WorkspaceContext::for($request);
    }

    /**
     * The file tree the browser needs: every file, plus every directory so an
     * empty folder still appears.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Workspace $workspace, array $extra = []): array
    {
        return [
            ...$extra,
            'files' => $workspace->files(),
            'directories' => $workspace->directories(),
        ];
    }
}
