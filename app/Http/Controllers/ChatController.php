<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ArtAgent;
use App\Ai\Agents\AssistantAgent;
use App\Ai\Agents\CodingAgent;
use App\Ai\Agents\DeepSearchAgent;
use App\Ai\Agents\EthicalHackingAgent;
use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\StudyAgent;
use App\Ai\ImageStyle;
use App\Ai\ModelCatalog;
use App\Ai\Models\LocalModel;
use App\Ai\Models\LocalModelRegistry;
use App\Ai\Models\ModelRunner;
use App\Ai\ResponseDepth;
use App\Ai\ResponseLength;
use App\Ai\ThinkingEffort;
use App\Ai\Tools\DeleteFileTool;
use App\Ai\Tools\ListFilesTool;
use App\Ai\Tools\ReadFileTool;
use App\Ai\Tools\SearchFilesTool;
use App\Ai\Tools\WriteFileTool;
use App\Mcp\McpTools;
use App\Workspace\Workspace;
use App\Workspace\WorkspaceContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Messages\Message;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ChatController extends Controller
{
    public function __construct(
        private readonly ModelCatalog $catalog,
        private readonly LocalModelRegistry $registry,
        private readonly ModelRunner $runner,
    ) {}

    /**
     * The assistant roles a request may select.
     */

    /**
     * The longest prompt the composer will accept.
     *
     * This has to match the textarea's maxlength, or a long paste that the
     * browser accepted would be rejected by the server.
     */
    private const MAX_MESSAGE_CHARACTERS = 20_000;

    /**
     * The attachment formats accepted with a chat message, mapped to the
     * largest size (in kilobytes) each may occupy.
     */
    private const ATTACHMENT_LIMITS = [
        'image/jpeg' => 5120,
        'image/png' => 5120,
        'image/gif' => 5120,
        'image/webp' => 5120,
        'video/mp4' => 25600,
        'video/webm' => 25600,
        'video/quicktime' => 25600,
    ];

    public function index(): View
    {
        return view('chat.index');
    }

    /**
     * The providers and models this application can actually run.
     *
     * The settings view and the header model picker both read this, so the
     * two can never disagree about what is available.
     */
    public function models(): JsonResponse
    {
        return response()->json($this->catalog->toArray());
    }

    /**
     * The local model files detected in the models directory.
     *
     * Detection is read-only and happens once per request; the underlying
     * registry caches each file's parsed header against its size and mtime.
     *
     * Runtime health costs a network probe, so it is opt-in with ?health=1.
     * The settings view asks for it when it opens, and a plain list of files
     * does not pay three seconds of connection timeouts for a status nobody
     * looked at.
     */
    public function localModels(Request $request, LocalModelRegistry $registry, ModelRunner $runner): JsonResponse
    {
        $payload = [
            'path' => $registry->path(),
            'models' => $registry->toArray(),
            'unreadable' => $registry->unreadable(),
        ];

        if ($request->boolean('health')) {
            // Only the runtimes the detected files would actually use are
            // probed. A diffusers URL matters when an image checkpoint is in
            // the directory and not otherwise, and each probe can cost its
            // full timeout waiting on a server that is not running.
            $payload['runtimes'] = $runner->healthFor(
                collect($registry->models())
                    ->map(fn (LocalModel $model): string => $model->runtime()['name'])
                    ->reject(fn (string $name): bool => $name === 'none')
                    ->unique()
                    ->values()
                    ->all()
            );
        }

        return response()->json($payload);
    }

    /**
     * The assistant roles and response depths the browser can choose from.
     *
     * The composer renders its pickers from this, so the UI can never offer a
     * mode or depth the server would reject.
     */
    public function capabilities(): JsonResponse
    {
        return response()->json([
            'modes' => self::modeCatalog(),
            'depths' => ResponseDepth::toArray(),
            'efforts' => ThinkingEffort::toArray(),
            'lengths' => ResponseLength::toArray(),
            'styles' => ImageStyle::toArray(),
            'mentions' => $this->mentionCatalog(),
        ]);
    }

    public function send(Request $request): Response
    {
        $validated = $request->validate([
            'message' => ['required_without:attachments', 'string', 'max:'.self::MAX_MESSAGE_CHARACTERS],
            'history' => ['sometimes', 'array', 'max:40'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:'.self::MAX_MESSAGE_CHARACTERS],
            'attachments' => ['sometimes', 'array', 'max:4'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,qt,mov', 'max:25600'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:64'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
            'mode' => ['sometimes', 'nullable', Rule::in(parent::MODES)],
            'depth' => ['sometimes', 'nullable', Rule::in(array_column(ResponseDepth::toArray(), 'id'))],
            'thinking' => ['sometimes', 'nullable', Rule::in(array_column(ThinkingEffort::toArray(), 'id'))],
            'length' => ['sometimes', 'nullable', Rule::in(array_column(ResponseLength::toArray(), 'id'))],
            'web' => ['sometimes', 'boolean'],
            'deep_search' => ['sometimes', 'boolean'],
            'mentions' => ['sometimes', 'array', 'max:10'],
            'mentions.*' => ['string', Rule::in(self::mentionableAgentNames())],
            'workspace' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $selection = $this->catalog->resolve(
            $validated['provider'] ?? null,
            $validated['model'] ?? null,
        );

        // A chat message needs a text model. The picker only offers text models,
        // but a stored selection can outlive a model swap in the directory, and
        // sending a prompt to an image checkpoint returns an empty stream that
        // reads as a broken runtime.
        $this->ensureModelAnswersText($selection);

        /** @var array<int, UploadedFile> $attachments */
        $attachments = collect($request->file('attachments') ?? [])
            ->filter(fn (UploadedFile $file): bool => $file->isValid())
            ->values()
            ->all();

        $this->ensureProviderAccepts($attachments, $selection['provider']);

        $history = collect($validated['history'] ?? [])
            ->map(fn (array $message): Message => new Message($message['role'], $message['content']))
            ->all();

        // A message is optional when files are attached, but the provider still
        // needs a non-empty prompt — fall back to a describe-the-attachment ask.
        $text = $validated['message'] ?? '';

        if ($text === '' && $attachments !== []) {
            $text = 'Describe the attached files and highlight anything noteworthy.';
        }

        // The response is streamed, so PHP's execution time limit has to come off.
        // It defaults to 30-120 seconds and a reasoning model routinely runs longer:
        // exceeding it is a fatal error, which kills the worker mid-stream instead of
        // being caught by the protocol, leaving the client with a truncated body and
        // no terminal part to tell it what happened.
        set_time_limit(0);

        $mode = $validated['mode'] ?? 'chat';
        $depth = ResponseDepth::fromRequest($validated['depth'] ?? null);
        $effort = ThinkingEffort::fromRequest($validated['thinking'] ?? null);
        $length = ResponseLength::fromRequest($validated['length'] ?? null);
        $web = (bool) filter_var($validated['web'] ?? false, FILTER_VALIDATE_BOOL);
        $deepSearch = (bool) filter_var($validated['deep_search'] ?? false, FILTER_VALIDATE_BOOL);

        // Search is inherent to the modes whose whole point is the web, so the
        // prompt must describe what actually happens rather than the raw
        // toggles. Anything else is opted into explicitly.
        $searchEnabled = $web
            || $deepSearch
            || in_array($mode, ['research', 'deep-search', 'security'], true);
        $deepSearchEnabled = $deepSearch || $mode === 'deep-search';

        $workspace = WorkspaceContext::for($request);

        $agent = new AssistantAgent(
            $workspace,
            instructions: $this->instructions($mode, $depth, $effort, $searchEnabled, $deepSearchEnabled, $validated['mentions'] ?? []),
            messages: $history,
            tools: $this->tools($mode, $workspace, $web || $searchEnabled, $deepSearch || $deepSearchEnabled),
            depth: $depth,
            effort: $effort,
            length: $length,
            model: $selection['model'],
        );

        // Local model files are served by a runtime on this machine, not by the
        // AI SDK, so they take a different path through the same endpoint. The
        // SDK has no driver for "a .gguf sitting in a directory", and handing it
        // one produced an empty reply rather than an error.
        if ($selection['provider'] === ModelCatalog::LOCAL_PROVIDER && filled($selection['model'])) {
            return $this->sendLocal($text, $history, $selection['model'], $request);
        }

        // The depth tunes step budget, sampling and reasoning effort for the
        // driver that ends up serving the request.
        $response = $agent->stream(
            $text,
            $attachments,
            provider: $selection['provider'],
            model: $selection['model'],
            timeout: 300,
        )
            ->usingVercelDataProtocol()
            ->toResponse($request);

        // The Vercel data protocol replaces the package's default SSE headers, which
        // carried this one. Without it nginx buffers the stream and every event
        // arrives at once when generation finishes.
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * Emit a Vercel-protocol Server-Sent Events stream.
     *
     * Headers match the SDK's own streaming response, including the buffering
     * hint: without it nginx holds the body until the runtime finishes, so the
     * reply appears all at once instead of arriving as it is generated.
     *
     * The callback is only invoked once the response has begun sending, so it
     * is safe to do work (including blocking HTTP) inside it.
     */
    private function streamResponse(Request $request, callable $producer): Response
    {
        $response = response()->stream(function () use ($producer): void {
            $emit = function (string $type, array $data = []): void {
                echo 'data: '.json_encode(['type' => $type, ...$data])."\n\n";
                flush();
            };

            try {
                $producer($emit);
            } catch (Throwable $e) {
                // A runtime that dies mid-stream must still close the protocol,
                // or the browser waits forever for a terminal event that the
                // failure already made impossible.
                $emit('error', ['message' => $e->getMessage()]);
            }

            $emit('finish');
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);

        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * Report a local-runtime failure in the same protocol the success path uses.
     *
     * A 500 with a plain JSON body would reach the composer as an unparseable
     * stream, so the message is delivered as an error event inside a
     * well-formed 200 stream instead.
     */
    private function streamError(Request $request, string $message): Response
    {
        return $this->streamResponse($request, function (callable $emit) use ($message): void {
            $emit('error', ['message' => $message]);
        });
    }

    /**
     * Stream a reply from a local model file through its runtime.
     *
     * The response body uses the same Server-Sent Events shape the Vercel data
     * protocol produces, so the browser's existing stream handler cannot tell
     * the difference between a hosted provider and a model running on this
     * machine. Emitting anything else would leave the composer waiting on a
     * terminal event that never arrives.
     *
     * @param  list<Message>  $history
     */
    private function sendLocal(string $text, array $history, string $model, Request $request): Response
    {
        $messages = collect($history)
            ->map(fn (Message $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->push(['role' => 'user', 'content' => $text])
            ->values()
            ->all();

        set_time_limit(0);

        try {
            $deltas = $this->runner->streamLocal($this->registry, $model, $messages, [
                'temperature' => 0.7,
                'timeout' => 300,
            ]);
        } catch (RuntimeException $e) {
            return $this->streamError($request, $e->getMessage());
        }

        return $this->streamResponse($request, function (callable $emit) use ($deltas): void {
            foreach ($deltas as $delta) {
                $emit('text-delta', ['delta' => $delta]);
            }
        });
    }

    /**
     * The system prompt for a chat mode.
     *
     * "chat" stays lean so ordinary conversation does not pay for a tool
     * round-trip; the specialist modes explain the delegation contract, since
     * a sub-agent cannot see the conversation it was dispatched from.
     *
     *     * @param  list<string>  $mentions  Sub-agents the user named with "@".
     */
    private function instructions(
        string $mode,
        ResponseDepth $depth,
        ThinkingEffort $effort,
        bool $web,
        bool $deepSearch,
        array $mentions = [],
    ): string {
        $role = match ($mode) {
            'ask' => 'You are Whale AI, answering a question. Read the workspace if it helps, but change nothing: you have no tools that can write. Answer directly and skip any preamble.',
            'plan' => 'You are Whale AI, planning an implementation. Investigate the workspace as much as you need, then produce a plan and stop. Your tools cannot write, so a plan is the whole deliverable. Cover the files to change, the steps in order, the risks and how to verify the result. Do not write the code.',
            'agent' => 'You are Whale AI, a coding agent. You can create, read, edit and delete files in the user\'s sandboxed workspace. Delegate the implementation to the coding_agent sub-agent with a complete, self-contained brief — it cannot see this conversation. Write the code, then say what changed and how to check it.',
            'orchestrate' => 'You are Whale AI, orchestrating a full piece of work end to end. Plan first, then implement by delegating to the coding_agent sub-agent with a self-contained brief, then verify: re-read what changed, and run the project\'s test suite or linter through the workspace tools. If a check fails, diagnose the cause and fix it before reporting. Report the plan you followed, the files changed and the result of each check.',
            'debug' => 'You are Whale AI, diagnosing a fault. Investigate only — your tools cannot change files. Reproduce the symptom from the code and the error text, form the most likely explanations, and rank them by evidence rather than guessing. Name the specific file and line you would change and why, but leave the edit to the user.',
            'code' => 'You are Whale AI, a coding assistant. You can create, read, edit and delete files in the user\'s sandboxed workspace. When a task involves writing or changing files, delegate to the coding_agent sub-agent with a complete, self-contained brief — it cannot see this conversation. Keep your own replies short and summarise what changed.',
            'research' => 'You are Whale AI, a research assistant. Use the research_agent sub-agent for a focused lookup, or the deep_search_agent sub-agent when the question needs several searches and cross-checking. Cite the sources returned. Keep the final answer concise and well-linked.',
            'deep-search' => 'You are Whale AI, an advanced research assistant. Prefer the deep_search_agent sub-agent: it plans several queries, fetches pages and reconciles conflicting sources. Give it the question and the constraints in a self-contained brief, then present its findings with the citations it returns.',
            'study' => 'You are Whale AI, a study assistant. Use the study_agent sub-agent to produce explanations, study plans, flashcards or quizzes. Include any source material in the brief, since the sub-agent cannot see this conversation.',
            'security' => 'You are Whale AI, an ethical-hacking study assistant. Use the ethical_hacking_agent sub-agent for questions about vulnerability classes, lawful penetration-testing methodology, defensive controls or certification study. It is scoped to authorised, defensive learning and will refuse offensive tooling; pass the user\'s question and any stated authorisation through in the brief.',
            'art' => 'You are Whale AI, a vector-art assistant. Use the art_agent sub-agent to create or edit SVG artwork in the workspace. Describe what you produced and how to open it.',
            default => 'You are Whale AI, a concise and helpful assistant for Laravel development.',
        };

        $prompt = $role.'\n\n'.$depth->instruction().'\n\n'.$effort->instruction();

        // The two toggles the composer exposes are stated in full rather than
        // implied by the tool list, because a sub-agent otherwise has no way to
        // know the user left them off on purpose.
        if ($deepSearch) {
            $prompt .= '\n\nWeb search is enabled in deep-search mode: run the deep_search_agent '
                .'sub-agent for the request, letting it plan several queries and cross-check sources. '
                .'Do not answer from memory alone.';
        } elseif ($web) {
            $prompt .= '\n\nWeb search is enabled: look up current information with your search tools '
                .'before answering anything that depends on facts which change.';
        } else {
            $prompt .= '\n\nWeb search is off for this turn. Do not claim to have searched the web, '
                .'and say plainly when an answer depends on information you cannot verify.';
        }

        // An explicit @-mention is a direct instruction to use that sub-agent,
        // so it is stated plainly rather than left to the model to infer.
        $named = array_values(array_intersect($mentions, self::mentionableAgentNames()));

        if ($named !== []) {
            $prompt .= '\n\nThe user explicitly named these sub-agents in their message: '
                .implode(', ', $named)
                .'. Delegate to them rather than working around them.';
        }

        return $prompt;
    }

    /**
     * The sub-agent names a user may @-mention.
     *
     * @return list<string>
     */
    private static function mentionableAgentNames(): array
    {
        return [
            'coding_agent',
            'research_agent',
            'deep_search_agent',
            'study_agent',
            'ethical_hacking_agent',
            'art_agent',
        ];
    }

    /**
     * The tools available to the assistant for a chat mode.
     *
     * Only "chat" runs without tools, so the common path stays a single
     * provider round-trip. Every other mode gets the file tools plus the
     * specialist sub-agents it might delegate to. The search toggles add the
     * web tools to whatever the mode already offers.
     *
     * @return list<object>
     */
    private function tools(string $mode, Workspace $workspace, bool $web = false, bool $deepSearch = false): array
    {
        if ($mode === 'chat' && ! $web && ! $deepSearch) {
            return [];
        }

        // Read-only modes get no way to change the workspace. This is enforced by
        // the tool list rather than by asking the model to behave, so "plan this"
        // cannot quietly rewrite a file.
        if ($this->isReadOnlyMode($mode) && ! $web && ! $deepSearch) {
            return $this->readOnlyTools($workspace);
        }

        $tools = $this->writeTools($workspace);

        $tools = match ($mode) {
            'chat' => [],
            'code', 'agent', 'orchestrate' => [...$tools, new CodingAgent($workspace)],
            'research' => [new ResearchAgent, new DeepSearchAgent],
            'deep-search' => [new DeepSearchAgent, new ResearchAgent],
            'study' => [new StudyAgent, new ResearchAgent],
            'security' => [new EthicalHackingAgent],
            'art' => [...$tools, new ArtAgent($workspace)],
            default => $tools,
        };

        if ($deepSearch && ! $this->hasTool($tools, DeepSearchAgent::class)) {
            $tools[] = new DeepSearchAgent;
        }

        if ($web && ! $this->hasTool($tools, ResearchAgent::class)) {
            $tools[] = new ResearchAgent;
        }

        // External MCP servers extend whatever the mode already offers. An
        // unconfigured install contributes nothing (see App\Mcp\McpTools),
        // so the common path is unchanged.
        return [...$tools, ...McpTools::configured()];
    }

    /**
     * Whether a mode is forbidden from changing the workspace.
     *
     * Planning and debugging are both investigations: the value is in the
     * reasoning, and a plan that quietly edits files is worse than no plan.
     */
    private function isReadOnlyMode(string $mode): bool
    {
        return in_array($mode, ['ask', 'plan', 'debug'], true);
    }

    /**
     * Tools that can look at the workspace but not change it.
     *
     * @return list<object>
     */
    private function readOnlyTools(Workspace $workspace): array
    {
        return [
            new ListFilesTool($workspace),
            new ReadFileTool($workspace),
            new SearchFilesTool($workspace),
        ];
    }

    /**
     * The full set of workspace tools, including the ones that write.
     *
     * @return list<object>
     */
    private function writeTools(Workspace $workspace): array
    {
        return [
            ...$this->readOnlyTools($workspace),
            new WriteFileTool($workspace),
            new DeleteFileTool($workspace),
        ];
    }

    /**
     * Whether a tool of the given class is already present.
     *
     * @param  list<object>  $tools
     */
    private function hasTool(array $tools, string $class): bool
    {
        foreach ($tools as $tool) {
            if ($tool instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * The assistant roles offered in the composer.
     *
     * @return list<array{id: string, label: string, description: string}>
     */
    private static function modeCatalog(): array
    {
        return [
            ['id' => 'ask', 'label' => 'Ask', 'description' => 'A question answered. Reads files, changes nothing.'],
            ['id' => 'plan', 'label' => 'Plan', 'description' => 'Investigate, then produce a plan. Writes nothing.'],
            ['id' => 'agent', 'label' => 'Agent', 'description' => 'Write the code and check it works.'],
            ['id' => 'debug', 'label' => 'Debug', 'description' => 'Diagnose a fault from evidence. Changes nothing.'],
            ['id' => 'orchestrate', 'label' => 'Orchestrate', 'description' => 'Plan, implement, verify, fix. The whole job.'],
            ['id' => 'chat', 'label' => 'Chat', 'description' => 'Everyday conversation, no tools.'],
            ['id' => 'code', 'label' => 'Code', 'description' => 'Build and edit files in your workspace.'],
            ['id' => 'research', 'label' => 'Research', 'description' => 'Current, source-backed answers from the web.'],
            ['id' => 'deep-search', 'label' => 'Deep search', 'description' => 'Multi-query research that cross-checks sources.'],
            ['id' => 'study', 'label' => 'Study', 'description' => 'Explanations, study plans and flashcards.'],
            ['id' => 'security', 'label' => 'Security study', 'description' => 'Ethical-hacking study for authorised, defensive work.'],
            ['id' => 'art', 'label' => 'Art', 'description' => 'Create and edit SVG vector artwork.'],
        ];
    }

    /**
     * The sub-agents a user can @-mention, so the composer can name them.
     *
     * @return list<array{id: string, label: string, description: string}>
     */
    private function mentionCatalog(): array
    {
        $workspace = WorkspaceContext::for(request());

        $agents = [
            new CodingAgent($workspace),
            new ResearchAgent,
            new DeepSearchAgent,
            new StudyAgent,
            new EthicalHackingAgent,
            new ArtAgent($workspace),
        ];

        return array_map(
            fn ($agent): array => [
                'id' => $agent->name(),
                'label' => str_replace('_', ' ', $agent->name()),
                'description' => (string) $agent->description(),
            ],
            $agents,
        );
    }

    /**
     * Reject a chat message sent to a local model that does not answer text.
     *
     * Only the local provider is checked. Hosted providers resolve their model
     * through configuration, which only ever lists models the SDK can send to;
     * a local file is whatever is in the directory right now, so a selection
     * saved yesterday can point at a checkpoint that was replaced with an image
     * or video one. Streaming to it produces an empty reply that reads like a
     * runtime failure rather than the misselection it is.
     *
     * @param  array{provider: string|null, model: string|null}  $selection
     */
    private function ensureModelAnswersText(array $selection): void
    {
        if ($selection['provider'] !== ModelCatalog::LOCAL_PROVIDER || blank($selection['model'])) {
            return;
        }

        $model = $this->registry->find($selection['model']);

        if ($model !== null && ! in_array('text', $model->capabilities(), true)) {
            throw ValidationException::withMessages([
                'model' => sprintf(
                    'The local model "%s" generates %s, not text. Pick a text model to chat.',
                    $selection['model'],
                    implode(' or ', array_diff($model->capabilities(), ['text'])),
                ),
            ]);
        }
    }

    /**
     * Reject attachments the configured provider would refuse mid-stream.
    /**
     * Reject attachments the configured provider would refuse mid-stream.
     *
     * Ollama's gateway maps images only — any other media type throws an
     * InvalidArgumentException while the stream is already being written, which
     * surfaces to the client as a truncated response instead of a validation error.
     *
     * @param  array<int, UploadedFile>  $attachments
     */
    private function ensureProviderAccepts(array $attachments, ?string $provider = null): void
    {
        if ($attachments === []) {
            return;
        }

        $provider ??= config('ai.default');

        $driver = config("ai.providers.{$provider}.driver");

        if ($driver !== 'ollama') {
            return;
        }

        $unsupported = collect($attachments)
            ->filter(fn (UploadedFile $file): bool => ! array_key_exists($file->getClientMimeType(), self::ATTACHMENT_LIMITS)
                || ! str_starts_with($file->getClientMimeType(), 'image/'))
            ->first();

        if ($unsupported !== null) {
            abort(
                422,
                'The selected model (Ollama) only accepts image attachments (JPEG, PNG, GIF, WebP). Switch to a multimodal provider such as Gemini to upload video files.',
            );
        }
    }
}
