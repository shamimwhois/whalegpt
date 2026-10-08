<h2>List available models</h2>
<p>
    <code>GET /ai/chat/models</code> returns every provider this install can reach and the models
    each one serves. The header picker is rendered from this, so a model the endpoint does not list
    is never offered.
</p>

<x-docs.code lang="http" label="Request">GET /ai/chat/models
Accept: application/json</x-docs.code>

<x-docs.code lang="json" label="Response">{
  "providers": [
    {
      "name": "ollama",
      "label": "Ollama",
      "configured": true,
      "models": [
        { "id": "qwen3:4b", "label": "Qwen3 4B", "type": "fast" }
      ]
    }
  ]
}</x-docs.code>

<h2>Local model files</h2>
<p>
    <code>GET /ai/chat/local-models</code> profiles the <code>.gguf</code> and
    <code>.safetensors</code> files in the models directory and reports text, image, video,
    audio and embedding capabilities inferred from each file's header. The application reads
    headers for metadata only; it never loads a model itself, so generation is delegated to a
    runtime such as llama.cpp. Files that cannot be read (including unfinished downloads) are
    listed under <code>unreadable</code> instead of being silently skipped.
</p>

<x-docs.code lang="http" label="Request">GET /ai/chat/local-models?health=1</x-docs.code>

<p>
    The optional <code>health</code> flag also probes the runtimes the detected files would
    actually use, so the settings view can tell a running llama-server from a stopped one.
</p>

<h2>Capabilities</h2>
<p>
    <code>GET /ai/chat/capabilities</code> describes the assistant modes, response depths, thinking
    efforts, response lengths, image styles and sub-agents the install supports. The composer builds
    its pickers from this one payload.
</p>

<x-docs.code lang="bash" label="Fetch capabilities">curl http://localhost:8000/ai/chat/capabilities \
  -H "Accept: application/json"</x-docs.code>
