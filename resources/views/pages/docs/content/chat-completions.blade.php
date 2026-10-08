<h2>Send a message</h2>
<p>
    <code>POST /ai/chat</code> sends one turn and streams the assistant's reply. The endpoint is
    rate limited to 20 requests a minute.
</p>

<x-docs.code lang="http" label="Request">POST /ai/chat
Accept: text/event-stream
Content-Type: application/json

{
  "message": "Summarise the last release note.",
  "history": [
    { "role": "user", "content": "Hello" },
    { "role": "assistant", "content": "Hi! How can I help?" }
  ],
  "mode": "code",
  "depth": "fast",
  "thinking": "medium",
  "length": "auto",
  "web": false
}</x-docs.code>

<h2>Fields</h2>
<table>
    <thead>
        <tr><th>Field</th><th>Type</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>message</code></td><td>string</td><td>Required unless files are attached. Up to 20,000 characters.</td></tr>
        <tr><td><code>history</code></td><td>array</td><td>Earlier turns, at most 40.</td></tr>
        <tr><td><code>mode</code></td><td>string</td><td>One of the assistant modes; <code>chat</code> by default.</td></tr>
        <tr><td><code>attachments</code></td><td>files</td><td>Up to four images or videos.</td></tr>
    </tbody>
</table>

<h2>The response</h2>
<p>
    The reply is streamed over the Vercel data protocol as <code>text/event-stream</code>. Each
    event carries a small JSON payload with a text delta, a tool call, or a terminal part. Read the
    stream incrementally rather than waiting for the body to finish.
</p>

<x-docs.code lang="bash" label="Stream with curl">curl -N -X POST http://localhost:8000/ai/chat \
  -H "Accept: text/event-stream" \
  -H "Content-Type: application/json" \
  -d '{"message":"Say hello in one sentence."}'</x-docs.code>

<h2>Related endpoints</h2>
<ul>
    <li><code>POST /ai/chat/image</code> — generate an image from a prompt.</li>
    <li><code>POST /ai/chat/audio</code> — generate speech or audio.</li>
    <li><code>POST /ai/chat/transcribe</code> — transcribe recorded audio.</li>
</ul>
