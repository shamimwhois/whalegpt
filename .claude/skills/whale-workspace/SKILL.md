---
name: whale-workspace
description: "Whale's sandboxed workspace and agent architecture. Use when adding or changing agent tools, sub-agents, workspace files, the code editor, the split-pane studio, projects/chat history, conversation export, or the MCP workspace server. Covers the App\\Workspace boundary, tool classes, and delegation rules."
license: MIT
metadata:
  author: whale
---

# Whale Workspace & Agents

This skill explains how the pieces of Whale fit together so new features land
in the right place. Read it before touching files, tools, agents or the studio.

## The workspace boundary

`App\Workspace\Workspace` is the single filesystem boundary. Every path is
untrusted: it is normalised, traversal and absolute paths are rejected, and the
resolved path is re-checked against the root. Never read or write workspace
files any other way — the IDE, the agent tools, the MCP server and the publish
flow all go through this class.

- One workspace per **client-held id** (`X-Whale-Workspace` header, stored in
  `localStorage` as `whale.workspace`). There is no session table.
- `App\Workspace\WorkspaceContext::for($request)` resolves it.
- `storage/app/workspaces/<id>/` holds the files.

## Agents

| Agent | File | Purpose |
| --- | --- | --- |
| `ChatAgent` | `app/Ai/Agents/ChatAgent.php` | Primary assistant; carries depth/effort to the provider. |
| `DeepSearchAgent` | `app/Ai/Agents/DeepSearchAgent.php` | Multi-query research that cross-checks sources. |
| `EthicalHackingAgent` | `app/Ai/Agents/EthicalHackingAgent.php` | Defensive, authorised security study. |
| `CodingAgent` | `app/Ai/Agents/CodingAgent.php` | Reads/writes workspace files. |
| `ResearchAgent` | `app/Ai/Agents/ResearchAgent.php` | Web search + fetch provider tools. |
| `StudyAgent` | `app/Ai/Agents/StudyAgent.php` | Explanations, plans, flashcards, quizzes. |
| `ArtAgent` | `app/Ai/Agents/ArtAgent.php` | Writes SVG markup into the workspace. |

Sub-agents implement `CanActAsTool`; passing an agent instance in `tools()`
wraps it automatically. **A sub-agent cannot see the parent conversation**, so
every delegation brief must be self-contained. Chat modes (`chat`, `code`,
`research`, `deep-search`, `study`, `security`, `art`) decide which tools are attached — `chat` stays
tool-free so the common path is one provider round-trip.

## Tools

Tools live in `app/Ai/Tools` and extend `WorkspaceTool`, which turns any thrown
exception into readable tool output so a failure never aborts the agent step.
Add a new tool by extending `WorkspaceTool` and returning it from the relevant
agent's `tools()`.

## Terminal

`App\Workspace\Terminal` interprets an allowlist of commands (`pwd ls cd cat
echo mkdir touch rm find grep head tail wc`) in PHP on top of the Workspace API
— there is no shell and no process is ever spawned. These routes carry no
authentication, so a real shell would be remote code execution; keep every
new command built from `Workspace` methods and re-validate paths through
`resolve()`. Endpoint: `POST chat/workspace/terminal` (`chat.workspace.terminal`);
the panel lives in `chat/workspace.blade.php`.

## MCP

`App\Mcp\Servers\WorkspaceServer` exposes the workspace over MCP at
`/mcp/workspace`. It is registered in `routes/ai.php`, which the MCP package
loads automatically (unprefixed). Application routes live in `routes/chat.php`
and are mounted under the `/ai` prefix — do not add app routes to `routes/ai.php`
or they will be registered twice, once without middleware.

External MCP servers listed in `config/mcp.php` are offered to agents through
`App\Mcp\McpTools::configured()`, which adapts each server tool with
`App\Mcp\McpTool`. Entries with no url or command are skipped before any
connection is attempted, so an unconfigured install adds nothing.

## Media

`MediaGenerationController` handles image, image edit, audio, OCR, vector (via
`ArtAgent`) and the video storyboard. The SDK has **no text-to-video provider**,
so "video" composes generated stills into a playable HTML storyboard written to
the workspace — it does not claim to be a real video model.

## Frontend

- `resources/views/chat/index.blade.php` — the chat app (Alpine, streaming).
- `resources/views/chat/studio.blade.php` — split-pane media studio.
- `resources/views/chat/workspace.blade.php` — the code editor / preview IDE.

All three read the workspace id from `window.whaleWorkspaceId()` in the layout.
