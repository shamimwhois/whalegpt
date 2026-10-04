<?php

namespace App\Workspace;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * A sandboxed filesystem rooted at a per-session directory.
 *
 * Every path handed to this class is treated as untrusted. Paths are
 * normalised and confined to the root, so a caller can never read, write or
 * delete anything outside its own workspace — even with "../" or an absolute
 * path. The IDE, the coding agent's tools and the publish flow all share this
 * single boundary.
 */
class Workspace
{
    /**
     * The file extensions the workspace will treat as text.
     *
     * Anything else is stored and served as a binary download rather than
     * being loaded into the editor.
     *
     * @var list<string>
     */
    public const TEXT_EXTENSIONS = [
        'txt', 'md', 'markdown', 'html', 'htm', 'css', 'scss', 'sass', 'less',
        'js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx', 'vue', 'svelte', 'json', 'jsonc',
        'php', 'blade.php', 'py', 'rb', 'go', 'rs', 'java', 'kt', 'c', 'h', 'cpp',
        'hpp', 'cs', 'swift', 'sh', 'bash', 'zsh', 'sql', 'yml', 'yaml', 'toml',
        'ini', 'env', 'xml', 'svg', 'csv', 'graphql', 'gql', 'twig', 'lock', 'gitignore',
    ];

    /**
     * Extensions served inline when previewing a workspace file in the browser.
     *
     * @var list<string>
     */
    public const PREVIEWABLE_EXTENSIONS = [
        'html', 'htm', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'mp4', 'webm',
        'pdf', 'txt', 'md', 'css', 'js', 'json',
    ];

    private readonly string $root;

    /**
     * The largest file, in bytes, the workspace will accept.
     */
    public const MAX_FILE_BYTES = 5_242_880;

    /**
     * The deepest directory nesting the workspace will accept.
     */
    public const MAX_DEPTH = 12;

    public function __construct(string $id)
    {
        $this->root = storage_path('app/workspaces/'.$this->sanitizeId($id));

        if (! is_dir($this->root) && ! mkdir($this->root, 0o755, true) && ! is_dir($this->root)) {
            throw new RuntimeException('Unable to create the workspace directory.');
        }
    }

    /**
     * Resolve the workspace for the given session identifier.
     */
    public static function forSession(string $sessionId): self
    {
        return new self($sessionId);
    }

    /**
     * The absolute path to the workspace root.
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * Every file in the workspace as a flat, sorted list.
     *
     * @return list<array{path: string, size: int, modified: int, binary: bool}>
     */
    public function files(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $relative = $this->relativePath($item->getPathname());

            $files[] = [
                'path' => $relative,
                'size' => $item->getSize(),
                'modified' => $item->getMTime(),
                'binary' => ! $this->isText($relative),
            ];
        }

        usort($files, fn (array $a, array $b): int => strcasecmp($a['path'], $b['path']));

        return $files;
    }

    /**
     * Every directory in the workspace as a flat, sorted list of relative paths.
     *
     * The root is deliberately absent, and empty folders are included — the
     * file list alone cannot represent a directory the user created but has
     * not filled yet.
     *
     * @return list<string>
     */
    public function directories(): array
    {
        $directories = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $directories[] = $this->relativePath($item->getPathname());
            }
        }

        $directories = array_values(array_unique($directories));
        sort($directories, SORT_NATURAL | SORT_FLAG_CASE);

        return $directories;
    }

    /**
     * Create a directory inside the workspace, including its parents.
     */
    public function makeDirectory(string $path): void
    {
        $absolute = $this->resolve($path, forWrite: true);

        if (is_dir($absolute)) {
            return;
        }

        if (! mkdir($absolute, 0o755, true) && ! is_dir($absolute)) {
            throw new RuntimeException("Unable to create the directory [{$path}].");
        }
    }

    /**
     * Read a file's contents.
     */
    public function read(string $path): string
    {
        $absolute = $this->resolve($path);

        if (! is_file($absolute)) {
            throw new RuntimeException("The file [{$path}] does not exist.");
        }

        $contents = file_get_contents($absolute);

        return $contents === false ? '' : $contents;
    }

    /**
     * Write contents to a file, creating parent directories as needed.
     */
    public function write(string $path, string $contents): void
    {
        if (strlen($contents) > self::MAX_FILE_BYTES) {
            throw new RuntimeException('The file exceeds the workspace size limit.');
        }

        $absolute = $this->resolve($path, forWrite: true);

        $directory = dirname($absolute);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the parent directory.');
        }

        if (file_put_contents($absolute, $contents) === false) {
            throw new RuntimeException("Unable to write the file [{$path}].");
        }
    }

    /**
     * Store an uploaded file inside the workspace.
     */
    public function storeUpload(UploadedFile $file, ?string $directory = null): string
    {
        $name = Str::of($file->getClientOriginalName())
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->limit(120, '')
            ->toString();

        if ($name === '') {
            $name = 'upload-'.Str::random(8);
        }

        $path = ltrim(($directory ? trim($directory, '/').'/' : '').$name, '/');

        // Never overwrite silently: find the first free "name-2.ext" variant.
        $path = $this->uniquePath($path);

        $this->write($path, (string) file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Delete a file from the workspace.
     */
    public function delete(string $path): void
    {
        $absolute = $this->resolve($path);

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    /**
     * Rename or move a file within the workspace.
     */
    public function move(string $from, string $to): void
    {
        $source = $this->resolve($from);
        $target = $this->resolve($to, forWrite: true);

        if (! is_file($source)) {
            throw new RuntimeException("The file [{$from}] does not exist.");
        }

        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the parent directory.');
        }

        if (! @rename($source, $target)) {
            throw new RuntimeException("Unable to move [{$from}] to [{$to}].");
        }
    }

    /**
     * Find files whose contents match a query.
     *
     * @return list<array{path: string, line: int, excerpt: string}>
     */
    public function search(string $query, int $limit = 50): array
    {
        $needle = Str::lower($query);
        $matches = [];

        if ($needle === '') {
            return [];
        }

        foreach ($this->files() as $file) {
            if ($file['binary'] || count($matches) >= $limit) {
                continue;
            }

            $lines = preg_split('/\R/', $this->read($file['path'])) ?: [];

            foreach ($lines as $number => $line) {
                if (Str::contains(Str::lower($line), $needle)) {
                    $matches[] = [
                        'path' => $file['path'],
                        'line' => $number + 1,
                        'excerpt' => trim(Str::limit($line, 160, '…')),
                    ];

                    if (count($matches) >= $limit) {
                        break;
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * Whether the workspace holds no files.
     */
    public function isEmpty(): bool
    {
        return $this->files() === [];
    }

    /**
     * Delete every file in the workspace.
     */
    public function flush(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }

    /**
     * Whether a path should be treated as text rather than binary.
     */
    public function isText(string $path): bool
    {
        $lower = Str::lower($path);

        foreach (self::TEXT_EXTENSIONS as $extension) {
            if (Str::endsWith($lower, '.'.$extension)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Confine an untrusted path to the workspace root.
     */
    public function resolve(string $path, bool $forWrite = false): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || Str::contains($path, "\0")) {
            throw new RuntimeException('A file path is required.');
        }

        // Reject absolute paths, drive letters and stream wrappers outright.
        if (Str::startsWith($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1 || Str::contains($path, '://')) {
            throw new RuntimeException('Absolute paths are not allowed inside the workspace.');
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new RuntimeException('Path traversal is not allowed.');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new RuntimeException('A file path is required.');
        }

        if (count($segments) > self::MAX_DEPTH) {
            throw new RuntimeException('The path is nested too deeply.');
        }

        $absolute = $this->root.'/'.implode('/', $segments);

        // Belt and braces: confirm the resolved path is still inside the root.
        // realpath() returns platform separators, so both sides are normalised
        // to forward slashes before the comparison or Windows paths never match.
        $realRoot = str_replace('\\', '/', realpath($this->root) ?: $this->root);
        $realParent = str_replace('\\', '/', realpath(dirname($absolute)) ?: dirname($absolute));

        if (! $forWrite && ! Str::startsWith($realParent.'/', rtrim($realRoot, '/').'/')) {
            throw new RuntimeException('The path escapes the workspace.');
        }

        return $absolute;
    }

    /**
     * A path relative to the workspace root.
     */
    private function relativePath(string $absolute): string
    {
        return ltrim(Str::after(str_replace('\\', '/', $absolute), str_replace('\\', '/', $this->root)), '/');
    }

    /**
     * Find a free path by suffixing "name-2", "name-3" and so on.
     */
    private function uniquePath(string $path): string
    {
        if (! is_file($this->root.'/'.$path)) {
            return $path;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = $extension === '' ? $path : Str::beforeLast($path, '.'.$extension);
        $suffix = $extension === '' ? '' : '.'.$extension;

        for ($i = 2; $i < 1000; $i++) {
            $candidate = "{$base}-{$i}{$suffix}";

            if (! is_file($this->root.'/'.$candidate)) {
                return $candidate;
            }
        }

        return $base.'-'.Str::random(6).$suffix;
    }

    /**
     * Reduce an arbitrary identifier to a safe directory name.
     */
    private function sanitizeId(string $id): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '', $id) ?? '';

        if (strlen($clean) < 8) {
            $clean = hash('sha256', $id);
        }

        return substr($clean, 0, 64);
    }
}
