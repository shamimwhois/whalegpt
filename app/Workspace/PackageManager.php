<?php

namespace App\Workspace;

use RuntimeException;

/**
 * Installs packages inside the workspace for the IDE terminal.
 *
 * Why this is opt-in
 * ------------------
 * `composer install`, `npm install` and `pip install` are not file operations:
 * each will happily run scripts from the package it just fetched (Composer's
 * post-install, npm's postinstall, a Python setup.py). Wiring that to an HTTP
 * endpoint turns any caller into remote code execution on the developer's
 * machine. The IDE terminal itself is deliberately a pure-PHP interpreter for
 * exactly this reason.
 *
 * So this runs only when switched on with WHALE_TERMINAL_PACKAGES, and even then
 * it is narrower than a real shell:
 *
 *   - only the `install` family of verbs is reachable; `run`, `exec` and
 *     `publish` are not commands this class understands
 *   - no shell is involved: arguments are passed as an array, so quoting, `;`,
 *     `&&` and backticks are inert
 *   - the working directory is always resolved inside the workspace
 *   - output is truncated and the process is killed at a hard timeout
 *
 * This is a developer tool for a machine its owner already controls. It is not
 * a feature to enable on anything reachable by someone else.
 */
class PackageManager
{
    /**
     * How long an install may run before it is killed.
     */
    private const TIMEOUT = 300;

    /**
     * How much output is returned to the browser.
     */
    private const MAX_OUTPUT = 20000;

    /**
     * The verbs each tool may run. Install verbs only: nothing that executes a
     * script the package defines, publishes, or reaches the network for the user.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'composer' => ['install', 'update', 'require'],
        'npm' => ['install', 'ci', 'i'],
        'pip' => ['install'],
    ];

    public function __construct(
        private readonly Workspace $workspace,
        private readonly bool $enabled = false,
    ) {}

    /**
     * Whether package installs are available at all.
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * The tools this terminal can install with, for the help text.
     *
     * @return list<string>
     */
    public function available(): array
    {
        return $this->enabled ? array_keys(self::ALLOWED) : [];
    }

    /**
     * Run one package command.
     *
     * @param  list<string>  $args
     * @return array{output: string, code: int, cwd: string}
     *
     * @throws RuntimeException when installs are off or the verb is not allowed.
     */
    public function run(string $tool, array $args, string $cwd = ''): array
    {
        if (! $this->enabled) {
            throw new RuntimeException(
                'Package installs are disabled. Set WHALE_TERMINAL_PACKAGES=true to allow them, and only on a machine you control.',
            );
        }

        $tool = strtolower($tool);

        if (! isset(self::ALLOWED[$tool])) {
            throw new RuntimeException("Unknown package tool [{$tool}].");
        }

        $verb = $this->firstArgument($args);

        if ($verb === null || ! in_array($verb, self::ALLOWED[$tool], true)) {
            throw new RuntimeException(sprintf(
                'Only "%s" is allowed for %s. Package scripts are not run here.',
                implode('" / "', self::ALLOWED[$tool]),
                $tool,
            ));
        }

        array_unshift($args, '--no-interaction');

        if ($tool === 'npm') {
            $args = array_merge(['--no-audit', '--no-fund'], $args);
        }

        $binary = $this->binaryFor($tool);

        if ($binary === null) {
            throw new RuntimeException(sprintf(
                '%s was not found on the web server\'s PATH. Set WHALE_%s_BINARY to its full path.',
                $tool,
                strtoupper($tool),
            ));
        }

        $command = [$binary];

        if ($tool === 'pip' && ! $this->isWindows()) {
            array_unshift($command, '-m');
        }

        $directory = $cwd === ''
            ? $this->workspace->root()
            : $this->insideWorkspace($cwd);

        return $this->execute(array_merge($command, $args), $directory, $tool, $cwd);
    }

    /**
     * Run the process and collect its output.
     *
     * @param  list<string>  $command
     * @return array{output: string, code: int, cwd: string}
     */
    private function execute(array $command, string $directory, string $tool, string $cwd): array
    {
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $directory,
        );

        if (! is_resource($process)) {
            throw new RuntimeException("Could not start {$tool}. Is it installed and on PATH?");
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $deadline = microtime(true) + self::TIMEOUT;
        $timedOut = false;

        // Both streams are drained together: a chatty installer would otherwise
        // fill a pipe buffer and deadlock waiting for a reader that never comes.
        while (true) {
            $read = array_values(array_filter([$pipes[1], $pipes[2]], 'is_resource'));

            if ($read !== []) {
                $write = [];
                $except = [];
                stream_select($read, $write, $except, 0, 200000);

                foreach ($read as $stream) {
                    $chunk = fread($stream, 8192);

                    if (is_string($chunk) && $chunk !== '') {
                        $output .= $chunk;
                    }
                }
            }

            if (! proc_get_status($process)['running']) {
                break;
            }

            if (microtime(true) > $deadline) {
                $timedOut = true;
                proc_terminate($process);

                break;
            }
        }

        foreach ($pipes as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $status = proc_close($process);

        if (strlen($output) > self::MAX_OUTPUT) {
            $output = substr($output, 0, self::MAX_OUTPUT)."\n… output truncated …";
        }

        if ($timedOut) {
            return [
                'output' => $output."\nStopped after ".self::TIMEOUT.'s.',
                'code' => 124,
                'cwd' => $cwd,
            ];
        }

        return ['output' => $output, 'code' => $status, 'cwd' => $cwd];
    }

    /**
     * The executable for a tool.
     *
     * A web server inherits a much smaller PATH than the shell a developer uses,
     * so each tool can also be pointed at directly. The path is resolved here so
     * a missing binary becomes a readable message instead of proc_open throwing
     * a "cannot find the file" warning that surfaces as a stack trace.
     */
    private function binaryFor(string $tool): ?string
    {
        $override = config("whale.terminal.binaries.{$tool}");

        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }

        $candidates = $tool === 'npm'
            ? ($this->isWindows() ? ['npm.cmd', 'npm'] : ['npm'])
            : ($tool === 'pip' && ! $this->isWindows() ? ['pip3', 'pip'] : [$tool]);

        foreach ($candidates as $candidate) {
            $found = $this->locate($candidate);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Find an executable on PATH, or as a shim in a known install location.
     */
    private function locate(string $command): ?string
    {
        $path = (string) getenv('PATH');

        foreach (array_filter(explode(PATH_SEPARATOR, $path)) as $directory) {
            foreach ($this->isWindows() ? [$command.'.exe', $command.'.bat', $command.'.cmd', $command] : [$command] as $file) {
                $candidate = rtrim($directory, '\\/').DIRECTORY_SEPARATOR.$file;

                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * The first argument that is not a flag.
     *
     * @param  list<string>  $args
     */
    private function firstArgument(array $args): ?string
    {
        foreach ($args as $arg) {
            if (! str_starts_with($arg, '-')) {
                return strtolower($arg);
            }
        }

        return null;
    }

    /**
     * Resolve a working directory, refusing anything outside the workspace.
     */
    private function insideWorkspace(string $cwd): string
    {
        $absolute = $this->workspace->resolve($cwd);
        $root = str_replace('\\', '/', $this->workspace->root());

        if (! str_starts_with(str_replace('\\', '/', $absolute), $root)) {
            throw new RuntimeException('The working directory is outside the workspace.');
        }

        return $absolute;
    }

    /**
     * Whether this is Windows, where the npm shim is a .cmd file.
     */
    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
