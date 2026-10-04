---
name: publish-and-export
description: "Exporting and publishing from Whale: workspace zip export, conversation export as txt/markdown/html/json/pdf, public share links, and the live preview. Use when adding download, share, publish or export features."
license: MIT
metadata:
  author: whale
---

# Publish & Export

## Workspace export

`WorkspaceController@export` streams the whole workspace as a zip. It reads each
file through `App\Workspace` — never assemble a zip from raw paths. A file that
vanishes mid-export is skipped rather than failing the download.

## Conversation export

`ConversationExportController` renders one transcript four ways:

| Format | Use |
| --- | --- |
| `txt` | Paste into notes or a terminal. |
| `md` | Commit as documentation. |
| `html` | Print to PDF from the browser. |
| `json` | Re-import as structured data. |
| `pdf` | Read or print a real PDF, written by `App\Support\SimplePdf` with no rendering library. |

Every format escapes user content; do not introduce an unescaped
interpolation when adding one. The PDF writer must keep escaping parens and
backslashes — unescaped text corrupts the file structure.

## Sharing

`POST chat/history/{conversation}/share` creates (or reveals) the thread's
`share_token` and returns the public URL; `DELETE` revokes it. The public
view is `GET chat/shared/{token}` (the `chat.shared` route, view
`chat.shared`), rendered read-only with no ownership check: the unguessable
token is the credential, and a revoked or unknown token 404s. Reuse
`Conversation::share_token` rather than inventing a second token column.

## Live preview

`WorkspaceController@preview` serves a single workspace file inline with a
content type chosen from its extension, and `X-Content-Type-Options: nosniff`.
The preview iframe is sandboxed (`allow-scripts allow-forms allow-popups
allow-modals`, never `allow-same-origin`), so previewed code cannot reach the
app's own origin or cookies. Keep it that way.

## Authorization

Every export and preview resolves the workspace from the client-held id and
confines paths to it. When adding a publish surface, reuse
`WorkspaceContext::for($request)` rather than inventing a new scope.
