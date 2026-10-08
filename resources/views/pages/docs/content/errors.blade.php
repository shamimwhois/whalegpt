<h2>Status codes</h2>
<p>
    Errors follow Laravel's convention. A JSON caller always receives a body with a
    <code>message</code>; a validation failure adds an <code>errors</code> object keyed by field.
</p>

<table>
    <thead>
        <tr><th>Code</th><th>Meaning</th></tr>
    </thead>
    <tbody>
        <tr><td><code>200</code></td><td>Success.</td></tr>
        <tr><td><code>401</code></td><td>Authentication is required and missing or invalid.</td></tr>
        <tr><td><code>403</code></td><td>Signed in, but not permitted — for example a non-admin opening <code>/admin</code>.</td></tr>
        <tr><td><code>404</code></td><td>No such route, documentation page, or OAuth provider.</td></tr>
        <tr><td><code>422</code></td><td>Validation failed. Inspect <code>errors</code> for the field.</td></tr>
        <tr><td><code>429</code></td><td>Rate limited. Wait for the <code>Retry-After</code> header.</td></tr>
        <tr><td><code>500</code></td><td>An unexpected server error. Check the application log.</td></tr>
    </tbody>
</table>

<h2>Validation errors</h2>

<x-docs.code lang="json" label="422 response">{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}</x-docs.code>

<h2>Rate limits</h2>
<p>
    Chat is limited to 20 requests a minute, image generation to 10, and transcription to 60,
    because live speech is sent as many short clips rather than one upload. Exceeding a limit
    returns <code>429</code>.
</p>
