<?php

namespace App\Workspace;

use InvalidArgumentException;
use RuntimeException;

/**
 * The IDE's allowlisted terminal.
 *
 * There is no shell here: every command is interpreted in PHP on top of the
 * Workspace API, so the allowlist below is the entire capability surface.
 * That is deliberate — these routes carry no authentication, and spawning a
 * real process would be remote code execution for anyone who found the
 * endpoint. Every path still passes through Workspace::resolve(), so "../"
 * cannot escape the workspace either.
 */
class Terminal
{
    /**
     * The commands this terminal understands, in help order.
     *
     * @var list<string>
     */
    public const COMMANDS = ['pwd', 'ls', 'cd', 'cat', 'echo', 'mkdir', 'touch', 'rm', 'find', 'grep', 'head', 'tail', 'wc'];

    public function __construct(private readonly Workspace $workspace) {}

    /**
     * Run one command line.
     *
     * @return array{output: string, code: int, cwd: string}
     *
     * @throws InvalidArgumentException When the command is not allowlisted.
     */
    public function run(string $line, string $cwd = ''): array
    {
        $tokens = preg_split('/\s+/', trim($line)) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token): bool => $token !== ''));

        if ($tokens === []) {
            throw new InvalidArgumentException('Type a command. Allowed: '.implode(', ', self::COMMANDS).'.');
        }

        $name = array_shift($tokens);

        if (! in_array($name, self::COMMANDS, true)) {
            throw new InvalidArgumentException("Unknown command [{$name}]. Allowed: ".implode(', ', self::COMMANDS).'.');
        }

        $cwd = trim(str_replace(chr(92), '/', $cwd), '/');

        try {
            $cwd = $this->cleanCwd($cwd);
            $result = $this->dispatch($name, $tokens, $cwd);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return ['output' => $e->getMessage(), 'code' => 1, 'cwd' => $cwd];
        }

        // cd reports the directory it moved into, which becomes the new cwd.
        if ($name === 'cd' && $result['code'] === 0) {
            $result['cwd'] = $result['output'];
        }

        $result['cwd'] = $cwd = $result['cwd'] ?? $cwd;

        return ['output' => $result['output'], 'code' => $result['code'], 'cwd' => $cwd];
    }

    /**
     * Validate a client-held working directory.
     *
     * @throws RuntimeException When the directory does not exist.
     */
    private function cleanCwd(string $cwd): string
    {
        if ($cwd === '') {
            return '';
        }

        $absolute = $this->workspace->resolve($cwd);

        if (! is_dir($absolute)) {
            throw new RuntimeException("No such directory: /{$cwd}");
        }

        return $cwd;
    }

    /**
     * Dispatch one allowlisted command.
     *
     * @param  list<string>  $args
     * @return array{output: string, code: int, cwd?: string}
     */
    private function dispatch(string $name, array $args, string $cwd): array
    {
        return match ($name) {
            'pwd' => $this->ok($cwd === '' ? '/' : '/'.$cwd),
            'echo' => $this->ok(implode(' ', $args)),
            'cd' => $this->changeDirectory($args, $cwd),
            'ls' => $this->listDirectory($args, $cwd),
            'cat' => $this->concatenate($args, $cwd),
            'mkdir' => $this->makeDirectory($args, $cwd),
            'touch' => $this->touch($args, $cwd),
            'rm' => $this->remove($args, $cwd),
            'find' => $this->find($args, $cwd),
            'grep' => $this->grep($args, $cwd),
            'head' => $this->sliceLines($args, $cwd, fromEnd: false),
            'tail' => $this->sliceLines($args, $cwd, fromEnd: true),
            'wc' => $this->countWords($args, $cwd),
        };
    }

    /**
     * @return array{output: string, code: int}
     */
    private function ok(string $output): array
    {
        return ['output' => $output, 'code' => 0];
    }

    /**
     * @return array{output: string, code: int}
     */
    private function fail(string $message): array
    {
        return ['output' => $message, 'code' => 1];
    }

    /**
     * Resolve a path relative to the working directory. Absolute-looking
     * paths (leading slash) are treated as workspace-root-relative, and the
     * Workspace re-validates every path we hand it.
     */
    private function qualify(string $path, string $cwd): string
    {
        $path = trim(str_replace(chr(92), '/', $path), '/');

        if ($path === '' || $cwd === '') {
            return $path;
        }

        if (! str_starts_with($path, '/')) {
            $path = $cwd.'/'.$path;
        }

        $parts = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $segment;
        }

        return implode('/', $parts);
    }

    /**
     * Whether a file can be shown as text: known text extension, or an
     * unknown extension without null bytes in its first kilobytes.
     */
    private function isReadable(string $path, string $absolute): bool
    {
        if ($this->workspace->isText($path)) {
            return true;
        }

        $head = file_get_contents($absolute, false, null, 0, 4096);

        return $head !== false && ! str_contains($head, chr(0));
    }

    /**
     * @param  list<string>  $args
     * @return array{output: string, code: int, cwd: string}
     */
    private function changeDirectory(array $args, string $cwd): array
    {
        $target = $args[0] ?? '';

        if ($target === '' || $target === '~' || $target === '/') {
            $path = '';
        } elseif ($target === '..') {
            $parts = $cwd === '' ? [] : explode('/', $cwd);
            array_pop($parts);
            $path = implode('/', $parts);
        } else {
            $path = $this->qualify($target, $cwd);
        }

        if ($path !== '') {
            $absolute = $this->workspace->resolve($path);

            if (! is_dir($absolute)) {
                return ['output' => "cd: {$target}: No such directory", 'code' => 1, 'cwd' => $cwd];
            }
        }

        return ['output' => $path, 'code' => 0, 'cwd' => $path];
    }

    /**
     * @param  list<string>  $args
     * @return array{output: string, code: int}
     */
    private function listDirectory(array $args, string $cwd): array
    {
        $showHidden = false;
        $target = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '-') && strlen($arg) > 1) {
                $showHidden = $showHidden || str_contains($arg, 'a');

                continue;
            }

            $target = $arg;
        }

        $directory = $target === null || $target === '.' ? $cwd : $this->qualify($target, $cwd);

        if ($directory !== '') {
            $absolute = $this->workspace->resolve($directory);

            if (! is_dir($absolute)) {
                return $this->fail("ls: {$target}: Not a directory");
            }
        }

        $prefix = $directory === '' ? '' : $directory.'/';
        $entries = [];

        foreach ($this->workspace->directories() as $known) {
            if (! str_starts_with($known, $prefix)) {
                continue;
            }

            $rest = substr($known, strlen($prefix));

            if ($rest === '' || str_contains($rest, '/') || (! $showHidden && str_starts_with($rest, '.'))) {
                continue;
            }

            $entries[$rest.'/'] = true;
        }

        foreach ($this->workspace->files() as $file) {
            if (! str_starts_with($file['path'], $prefix)) {
                continue;
            }

            $rest = substr($file['path'], strlen($prefix));

            if (str_contains($rest, '/') || (! $showHidden && str_starts_with($rest, '.'))) {
                continue;
            }

            $entries[$rest] = true;
        }

        $names = array_keys($entries);

        usort($names, function (string $a, string $b): int {
            $aDirectory = str_ends_with($a, '/');
            $bDirectory = str_ends_with($b, '/');

            if ($aDirectory !== $bDirectory) {
                return $aDirectory ? -1 : 1;
            }

            return strcasecmp($a, $b);
        });

        return $this->ok(implode("\n", $names));
    }

    /**
     * @param  list<string>  $args
     * @return array{output: string, code: int}
     */
    private function concatenate(array $args, string $cwd): array
    {
        if ($args === []) {
            return $this->fail('cat: missing file operand');
        }

        $blocks = [];
        $code = 0;

        foreach ($args as $arg) {
            $path = $this->qualify($arg, $cwd);

            try {
                $absolute = $this->workspace->resolve($path);

                if (! is_file($absolute)) {
                    $blocks[] = "cat: {$arg}: No such file";
                    $code = 1;

                    continue;
                }

                if (! $this->isReadable($path, $absolute)) {
                    $blocks[] = "cat: {$arg}: Binary file (not shown)";
                    $code = 1;

                    continue;
                }

                $blocks[] = rtrim($this->workspace->read($path), "\n");
            } catch (RuntimeException $e) {
                $blocks[] = 'cat: '.$e->getMessage();
                $code = 1;
            }
        }

        return ['output' => implode("\n", $blocks), 'code' => $code];
    }

    /**
     * @param  list<string>  $args
     * @return array{output: string, code: int}
     */
    private function makeDirectory(array $args, string $cwd): array
    {
        if ($args === []) {
            return $this->fail('mkdir: missing operand');
        }

        $path = $this->qualify($args[0], $cwd);

        if ($path === '') {
            return $this->fail('mkdir: cannot create the workspace root');
        }

        try {
            $absolute = $this->workspace->resolve($path, forWrite: true);

            if (is_dir($absolute) || is_file($absolute)) {
                return $this->fail("mkdir: {$args[0]}: File exists");
            }

            $this->workspace->makeDirectory($path);
        } catch (RuntimeException $e) {
            return $this->fail('mkdir: '.$e->getMessage());
        }

        return $this->ok('');
    }

    /**
     * @param  list<string>  $args
     * @return array{output: string, code: int}
     */
    private function touch(array $args, string $cwd): array
    {
        if ($args === []) {
            return $this->fail('touch: missing file operand');
        }

        $path = $this->qualify($args[0], $cwd);

        if ($path === '') {
            return $this->fail('touch: invalid path');
        }

        try {
            $absolute = $this->workspace->resolve($path, forWrite: true);

            if (is_dir($absolute)) {
                return $this->fail("touch: {$args[0]}: Is a directory");
            }

            if (! is_file($absolute)) {
                $this->workspace->write($path, '');
            }
        } catch (RuntimeException $e) {
            return $this->fail('touch: '.$e->getMessage());
        }

        return $this->ok('');
    }

    /**
     * @param  list<string>  $args
     * @return array{output: string, code: int}
     */
    private function remove(array $args, string $cwd): array
    {
        $recursive = false;
        $targets = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '-') && strlen($arg) > 1) {
                $recursive = $recursive || str_contains($arg, 'r');

                continue;
            }

            $targets[] = $arg;
        }

        if ($targets === []) {
            return $this->fail('rm: missing operand');
        }

        $code = 0;
        $messages = [];

        foreach ($targets as $target) {
            $path = $this->qualify($target, $cwd);

            try {
                $absolute = $this->workspace->resolve($path);

                if (is_dir($absolute)) {
                    if (! $recursive) {
                        $messages[] = "rm: {$target}: Is a directory (use rm -r)";
                        $code = 1;

                        continue;
                    }

                    $this->deleteTree($absolute);
                } elseif (is_file($absolute)) {
                    $this->workspace->delete($path);
                } else {
                    $messages[] = "rm: {$target}: No such file or directory";
                    $code = 1;
                }
            } catch (RuntimeException $e) {
                $messages[] = 'rm: '.$e->getMessage();
                $code = 1;
            }
        }

        return ['output' => implode("\n", $messages), 'code' => $code];
    }

    /**
     * Delete a directory tree. The path has already been confined by
     * Workspace::resolve(), so this can never reach outside the workspace.
     */
    private function deleteTree(string $absolute): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($absolute);
    }

    /**
     * @param  list<string>  $args
     * @return array{output: string, code: int}
     */
    private function find(array $args, string $cwd): array
    {
        $target = $args === [] || str_starts_with($args[0], '-') ? '' : $this->qualify($args[0], $cwd);
        $prefix = $target === '' ? '' : $target.'/';

        if ($target !== '') {
            $absolute = $this->workspace->resolve($target);

            if (! is_dir($absolute)) {
                return $this->fail("find: {$args[0]}: Not a directory");
            }
        }

        $entries = [];

        foreach ($this->workspace->directories() as $directory) {
            if (str_starts_with($directory, $prefix)) {
                $entries[] = $directory.'/';
            }
        }

        foreach ($this->workspace->files() as $file) {
            if (str_starts_with($file['path'], $prefix)) {
                $entries[] = $file['path'];
            }
        }

        sort($entries, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->ok(implode("\n", $entries));
    }

    /**
     * Case-insensitive plain-text search inside one file or directory,
     * reported as path:line: excerpt.
     *
     * @param  list<string>  $args
     * @return array{output: string, code: int}
     */
    private function grep(array $args, string $cwd): array
    {
        if (count($args) < 2) {
            return $this->fail('usage: grep <text> <path> (case-insensitive)');
        }

        $needle = $args[0];
        $target = $this->qualify($args[1], $cwd);

        try {
            $absolute = $this->workspace->resolve($target);
        } catch (RuntimeException $e) {
            return $this->fail('grep: '.$e->getMessage());
        }

        if (is_file($absolute)) {
            $prefix = null;
        } elseif (is_dir($absolute)) {
            $prefix = $target === '' ? '' : $target.'/';
        } else {
            return $this->fail("grep: {$args[1]}: No such file or directory");
        }

        $lines = [];

        foreach ($this->workspace->search($needle, 200) as $match) {
            $sameFile = $prefix === null && $match['path'] === $target;
            $underDirectory = $prefix !== null && str_starts_with($match['path'], $prefix);

            if (! $sameFile && ! $underDirectory) {
                continue;
            }

            $lines[] = "{$match['path']}:{$match['line']}: {$match['excerpt']}";
        }

        if ($lines === []) {
            return $this->fail('No matches found.');
        }

        return ['output' => implode("\n", $lines), 'code' => 0];
    }

    /**
     * head / tail: the first or last N lines of a text file.
     *
     * @param  list<string>  $args
     * @return array{output: string, code: int}
     */
    private function sliceLines(array $args, string $cwd, bool $fromEnd): array
    {
        $verb = $fromEnd ? 'tail' : 'head';
        $count = 10;
        $target = null;

        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === '-n' && isset($args[$i + 1]) && ctype_digit($args[$i + 1])) {
                $count = max(1, (int) $args[$i + 1]);
                $i++;

                continue;
            }

            if (preg_match('/^-\d+$/', $args[$i]) === 1) {
                $count = max(1, (int) substr($args[$i], 1));

                continue;
            }

            $target = $args[$i];
        }

        if ($target === null) {
            return $this->fail("{$verb}: missing file operand");
        }

        $path = $this->qualify($target, $cwd);

        try {
            $absolute = $this->workspace->resolve($path);

            if (! is_file($absolute)) {
                return $this->fail("{$verb}: {$target}: No such file");
            }

            if (! $this->isReadable($path, $absolute)) {
                return $this->fail("{$verb}: {$target}: Binary file (not shown)");
            }

            $lines = preg_split('/\R/', rtrim($this->workspace->read($path), "\n")) ?: [];
        } catch (RuntimeException $e) {
            return $this->fail($verb.': '.$e->getMessage());
        }

        $slice = $fromEnd ? array_slice($lines, -$count) : array_slice($lines, 0, $count);

        return $this->ok(implode("\n", $slice));
    }

    /**
     * wc: lines, words, bytes and the file name.
     *
     * @param  list<string>  $args
     * @return array{output: string, code: int}
     */
    private function countWords(array $args, string $cwd): array
    {
        if ($args === []) {
            return $this->fail('wc: missing file operand');
        }

        $path = $this->qualify($args[0], $cwd);

        try {
            $absolute = $this->workspace->resolve($path);

            if (! is_file($absolute)) {
                return $this->fail("wc: {$args[0]}: No such file");
            }

            if (! $this->isReadable($path, $absolute)) {
                return $this->fail("wc: {$args[0]}: Binary file");
            }

            $contents = $this->workspace->read($path);
        } catch (RuntimeException $e) {
            return $this->fail('wc: '.$e->getMessage());
        }

        $lines = $contents === ''
            ? 0
            : substr_count($contents, "\n") + (str_ends_with($contents, "\n") ? 0 : 1);

        $words = array_filter(
            preg_split('/\s+/', trim($contents)) ?: [],
            fn (string $word): bool => $word !== '',
        );

        return $this->ok(sprintf('%d %d %d %s', $lines, count($words), strlen($contents), $args[0]));
    }
}
