<h2>Overview</h2>
<p>
    Whale AI is a Laravel application that puts a single workspace in front of any model you can
    reach: a local runtime, a file on disk, or a hosted OpenAI-compatible endpoint. The browser
    talks to the same HTTP API documented here, so every example is a real request the product
    makes.
</p>

<h2>Base URL</h2>
<p>
    Every API route is mounted under the <code>/ai</code> prefix. In local development that is:
</p>

<x-docs.code lang="text" label="Base URL">http://localhost:8000/ai</x-docs.code>

<h2>Conventions</h2>
<ul>
    <li>Requests and responses are JSON unless stated otherwise; send <code>Accept: application/json</code>.</li>
    <li>Chat responses are streamed, so the transcript arrives as it is generated.</li>
    <li>Validation failures return <code>422</code> with a <code>message</code> and an <code>errors</code> object.</li>
    <li>Rate-limited endpoints answer <code>429</code> with a <code>Retry-After</code> header.</li>
</ul>

<h2>What lives where</h2>
<table>
    <thead>
        <tr><th>Area</th><th>Prefix</th><th>Guide</th></tr>
    </thead>
    <tbody>
        <tr><td>Chat and media</td><td><code>/ai/chat</code></td><td><a href="{{ route('docs.show', 'chat-completions') }}">Chat completions</a></td></tr>
        <tr><td>Models</td><td><code>/ai/chat/models</code></td><td><a href="{{ route('docs.show', 'models') }}">Models</a></td></tr>
        <tr><td>Accounts</td><td><code>/auth</code></td><td><a href="{{ route('docs.show', 'authentication') }}">Authentication</a></td></tr>
    </tbody>
</table>
