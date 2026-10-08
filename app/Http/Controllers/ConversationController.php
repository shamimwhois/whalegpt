<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Project;
use App\Workspace\WorkspaceContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Sidebar management: projects, their conversations, pinning, renaming,
 * reordering and archiving.
 *
 * Threads are scoped to the session (or the authenticated user when present)
 * so one visitor can never read another's history.
 */
class ConversationController extends Controller
{
    /**
     * How many archived threads the sidebar offers to restore.
     *
     * The archive is a safety net rather than a second inbox, so it is
     * deliberately bounded: an unbounded list would grow for the life of the
     * session and be paid for on every history load.
     */
    private const ARCHIVED_PREVIEW_LIMIT = 50;

    /**
     * The sidebar tree: projects with their conversations, plus unfiled threads.
     */
    public function index(Request $request): JsonResponse
    {
        $projects = $this->projectsQuery($request)
            ->with(['conversations' => fn ($query) => $query
                ->whereNull('archived_at')
                ->orderByDesc('pinned_at')
                ->orderBy('position')])
            ->orderBy('position')
            ->get();

        $unfiled = $this->conversationsQuery($request)
            ->whereNull('project_id')
            ->whereNull('archived_at')
            ->orderByDesc('pinned_at')
            ->orderByDesc('updated_at')
            ->get();

        // Sent with the tree so the archive view costs no extra round trip.
        $archived = $this->conversationsQuery($request)
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->limit(self::ARCHIVED_PREVIEW_LIMIT)
            ->get();

        return response()->json([
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
                'conversations' => $project->conversations->map($this->conversationPayload(...))->all(),
            ])->all(),
            'conversations' => $unfiled->map($this->conversationPayload(...))->all(),
            'archived' => $archived->map($this->conversationPayload(...))->all(),
        ]);
    }

    /**
     * Create a conversation (a new chat thread).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mode' => ['sometimes', Rule::in(parent::MODES)],
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
        ]);

        $conversation = new Conversation([
            'title' => $validated['title'] ?? 'New chat',
            'mode' => $validated['mode'] ?? 'chat',
            'project_id' => $validated['project_id'] ?? null,
            'position' => 0,
        ]);

        $this->associate($conversation, $request);
        $conversation->save();

        return response()->json([
            'conversation' => $this->conversationPayload($conversation),
        ], 201);
    }

    /**
     * Rename, pin, move or archive a conversation.
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'pinned' => ['sometimes', 'boolean'],
            'archived' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'mode' => ['sometimes', Rule::in(parent::MODES)],
        ]);

        if (array_key_exists('title', $validated)) {
            $conversation->title = $validated['title'];
        }

        if (array_key_exists('project_id', $validated)) {
            $conversation->project_id = $validated['project_id'];
        }

        if (array_key_exists('mode', $validated)) {
            $conversation->mode = $validated['mode'];
        }

        if (array_key_exists('position', $validated)) {
            $conversation->position = $validated['position'];
        }

        if (array_key_exists('pinned', $validated)) {
            $conversation->pinned_at = $validated['pinned'] ? now() : null;
        }

        if (array_key_exists('archived', $validated)) {
            $conversation->archived_at = $validated['archived'] ? now() : null;
        }

        $conversation->save();

        return response()->json(['conversation' => $this->conversationPayload($conversation)]);
    }

    /**
     * Delete a conversation and its messages.
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * The full message history for a conversation.
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        return response()->json([
            'conversation' => $this->conversationPayload($conversation),
            'messages' => $conversation->messages()
                ->get()
                ->map(fn (ChatMessage $message): array => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'kind' => $message->kind,
                    'content' => $message->content,
                    'meta' => $message->meta,
                    'position' => $message->position,
                ])->all(),
        ]);
    }

    /**
     * Append a message to a conversation.
     */
    public function appendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['user', 'assistant', 'system'])],
            'kind' => ['sometimes', 'string', 'max:24'],
            'content' => ['present', 'string', 'max:200000'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ]);

        $message = $conversation->messages()->create([
            'role' => $validated['role'],
            'kind' => $validated['kind'] ?? 'text',
            'content' => $validated['content'],
            'meta' => $validated['meta'] ?? null,
            'position' => (int) $conversation->messages()->max('position') + 1,
        ]);

        $conversation->touch();

        return response()->json([
            'message' => [
                'id' => $message->id,
                'role' => $message->role,
                'kind' => $message->kind,
                'content' => $message->content,
                'position' => $message->position,
            ],
        ], 201);
    }

    /**
     * Create a project.
     */
    public function storeProject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['sometimes', 'string', 'max:24'],
        ]);

        $project = new Project([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? 'emerald',
            'position' => (int) $this->projectsQuery($request)->max('position') + 1,
        ]);

        $this->associate($project, $request);
        $project->save();

        return response()->json(['project' => $project], 201);
    }

    /**
     * Rename or recolour a project.
     */
    public function updateProject(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'color' => ['sometimes', 'string', 'max:24'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $project->fill($validated)->save();

        return response()->json(['project' => $project]);
    }

    /**
     * Delete a project. Its conversations are kept and become unfiled.
     */
    public function destroyProject(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $project->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationPayload(Conversation $conversation): array
    {
        $latest = $conversation->latestMessage();

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'mode' => $conversation->mode,
            'project_id' => $conversation->project_id,
            'pinned' => $conversation->pinned_at !== null,
            'preview' => $latest?->content ? str($latest->content)->limit(80)->toString() : '',
            // The sidebar groups threads by date and sorts them, neither of
            // which a "3 hours ago" string can do, so the machine-readable
            // value travels alongside the human one.
            'updated_at' => $conversation->updated_at?->toIso8601String(),
            'updated_human' => $conversation->updated_at?->diffForHumans(),
        ];
    }

    /**
     * Attach the owning session or user to a new model.
     */
    private function associate(Project|Conversation $model, Request $request): void
    {
        $model->session_id = $this->ownerKey($request);
        $model->user_id = $request->user()?->id;
    }

    /**
     * @return Builder<Project>
     */
    private function projectsQuery(Request $request): Builder
    {
        return Project::query()->where(fn (Builder $query) => $query
            ->where('session_id', $this->ownerKey($request))
            // Only a real user adds a second way to own a row. Passing a null id
            // through would compile to `user_id is null`, which matches every
            // session-less row in the table — so a logged-out visitor would be
            // shown other visitors' projects.
            ->when(
                $request->user() !== null,
                fn (Builder $query) => $query->orWhere('user_id', $request->user()->id),
            ));
    }

    /**
     * Turn a thread into a read-only public page: create (or reveal) its
     * share token and return the link that addresses it.
     */
    public function share(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        if ($conversation->share_token === null) {
            $conversation->forceFill(['share_token' => Str::random(40)])->save();
        }

        return response()->json([
            'shared' => true,
            'token' => $conversation->share_token,
            'url' => route('chat.shared', ['token' => $conversation->share_token]),
        ]);
    }

    /**
     * Revoke sharing: the public link stops resolving immediately.
     */
    public function unshare(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->forceFill(['share_token' => null])->save();

        return response()->json(['shared' => false]);
    }

    /**
     * The public, read-only view of a shared thread. There is deliberately
     * no ownership check: the unguessable token is the credential, and an
     * unknown or revoked token simply resolves to a 404.
     */
    public function shared(string $token): View
    {
        $conversation = Conversation::where('share_token', $token)->firstOrFail();

        return view('chat.shared', [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->get(),
        ]);
    }

    /**
     * @return Builder<Conversation>
     */
    private function conversationsQuery(Request $request): Builder
    {
        return Conversation::query()->where(fn (Builder $query) => $query
            ->where('session_id', $this->ownerKey($request))
            // See projectsQuery: a null user id is not "any owner", it is
            // `user_id is null`, which would list every unclaimed conversation
            // to any logged-out browser.
            ->when(
                $request->user() !== null,
                fn (Builder $query) => $query->orWhere('user_id', $request->user()->id),
            ));
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        $owns = $conversation->session_id === $this->ownerKey($request)
            || ($request->user() !== null && $conversation->user_id === $request->user()->id);

        abort_unless($owns, 403);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        $owns = $project->session_id === $this->ownerKey($request)
            || ($request->user() !== null && $project->user_id === $request->user()->id);

        abort_unless($owns, 403);
    }

    /**
     * The client-held identifier that owns a project or conversation.
     *
     * Reusing the workspace id means one visitor's history, projects and files
     * all line up under a single key, with no session table required.
     */
    private function ownerKey(Request $request): string
    {
        return WorkspaceContext::idFrom($request);
    }
}
