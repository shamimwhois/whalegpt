<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local Model Files
    |--------------------------------------------------------------------------
    |
    | Drop .gguf files in this directory and they are detected, profiled and
    | offered in the chat model picker automatically. Nothing is uploaded and
    | no model is loaded by the application itself: the weights are read for
    | metadata only, and generation is delegated to a local runtime.
    |
    */

    'models_path' => env('WHALE_MODELS_PATH', base_path('app/Ai/Models')),

    /*
    |--------------------------------------------------------------------------
    | Local Runtimes
    |--------------------------------------------------------------------------
    |
    | The application never executes a model file. Text, vision and embedding
    | models are served by llama.cpp's OpenAI-compatible endpoint, and image
    | models need a Python runtime (diffusers or ComfyUI). These URLs are only
    | used to report whether a runtime is reachable.
    |
    */

    'runtimes' => [

        // Ollama, which is already configured for the Laravel AI SDK.
        //
        // It serves models pulled into its own store rather than files from a
        // directory, so it is never chosen to serve a local .gguf. It stays
        // here because the SDK uses it for hosted-provider traffic.
        'ollama' => [
            // Ollama's own default, so a runtime works without OLLAMA_URL being
            // set. config/ai.php falls back to the same address for the driver.
            'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
            'supports' => ['text', 'vision', 'embeddings'],
            'serves_files' => false,

            // Ollama keeps its own store, so the catalog asks it which models
            // have been pulled instead of reading one env var per model.
            'driver' => 'ollama',
            'discovers_models' => true,
        ],

        // llama.cpp server: `llama-server --model <file.gguf> --port 8080`
        'llama_cpp' => [
            'url' => env('LOCAL_LLM_URL'),
            'supports' => ['text', 'vision', 'embeddings'],
            'serves_files' => true,
        ],

        // Image generation needs Python: diffusers or a ComfyUI instance.
        'diffusers' => [
            'url' => env('LOCAL_IMAGE_URL'),
            'supports' => ['image'],
            'serves_files' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime Endpoints
    |--------------------------------------------------------------------------
    |
    | Paths appended to a runtime's base URL, for runtimes that do not follow
    | the OpenAI layout exactly.
    |
    */

    'runtimes_paths' => [
        'models_path' => env('WHALE_RUNTIME_MODELS_PATH', '/v1/models'),
        'chat_path' => env('WHALE_RUNTIME_CHAT_PATH', '/v1/chat/completions'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Capability Detection
    |--------------------------------------------------------------------------
    |
    | GGUF files carry no capability flags, so they are inferred from the
    | architecture name and the tensor names in the file. Disable this to only
    | ever report what a runtime explicitly advertises.
    |
    */

    'detect_capabilities' => (bool) env('WHALE_DETECT_CAPABILITIES', true),

    /*
    |--------------------------------------------------------------------------
    | Metadata Cache
    |--------------------------------------------------------------------------
    |
    | Parsing a multi-gigabyte model file's header is cheap but not free, so
    | results are cached against the file's size and modification time.
    |
    */

    'cache' => [
        'store' => env('WHALE_MODEL_CACHE', 'file'),
        'ttl' => (int) env('WHALE_MODEL_CACHE_TTL', 3600),
    ],

];
