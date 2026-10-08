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
    | Custom Providers
    |--------------------------------------------------------------------------
    |
    | Any number of extra OpenAI-compatible endpoints, declared as JSON so one
    | variable covers all of them:
    |
    |   WHALE_CUSTOM_PROVIDERS='[
    |     {"name":"lmstudio","url":"http://127.0.0.1:1234/v1","key":""},
    |     {"name":"gateway","url":"https://ai.example.com/v1","key":"sk-..."}
    |   ]'
    |
    | App\Ai\CustomProviders reads this, and AppServiceProvider merges the
    | result into config('ai.providers') so each one is resolvable by name.
    |
    */

    'custom_providers' => env('WHALE_CUSTOM_PROVIDERS'),

    /*
    |--------------------------------------------------------------------------
    | Custom Provider Model Listing
    |--------------------------------------------------------------------------
    |
    | Appended to a custom provider's base URL to ask it what it serves. It is
    | relative to that URL, unlike runtimes_paths above, because a custom
    | provider URL is an OpenAI-compatible base and already ends in /v1.
    |
    | A provider declared as http://127.0.0.1:1234/v1 is therefore asked at
    | http://127.0.0.1:1234/v1/models. Declare the URL without /v1 and set this
    | to /v1/models.
    |
    */

    'custom_providers_models_path' => env('WHALE_CUSTOM_PROVIDERS_MODELS_PATH', '/models'),

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
        //
        // The URL defaults to llama.cpp's conventional port so a runtime is
        // considered configured the moment it is started, with nothing to set.
        // This is a statement about configuration, not health: whether the
        // server is actually answering is answered separately by
        // App\Ai\Models\ModelRunner::health(), which makes a bounded request.
        'llama_cpp' => [
            'url' => env('LOCAL_LLM_URL', 'http://127.0.0.1:8080'),
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
    | Speech To Text
    |--------------------------------------------------------------------------
    |
    | Speech is transcribed by the first engine that is available:
    |
    |   1. Whistle, the 16.9 MB on-device model from Cactus Compute. It is a
    |      native binary, not a PHP library, so it is invoked as a subprocess
    |      with a 16 kHz mono WAV. It costs nothing per minute and needs no key.
    |   2. The AI SDK, using whichever provider config/ai.php resolves for the
    |      `transcription` capability.
    |
    | Whistle expects 16 kHz mono audio and handles up to 30 seconds in one
    | pass, so a longer recording is split into segments before it is sent.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Off by default: this is a single-user tool that usually runs on the
    | developer's own machine, and there is no account creation flow to sign
    | up with. Turn it on once sign-in is wanted, in which case a browser is
    | sent to the login route and a fetch() caller receives a 401.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Terminal Packages
    |--------------------------------------------------------------------------
    |
    | composer install, npm install and pip install run scripts from whatever
    | they fetch, so they are remote code execution by another name. They are off
    | by default and should only ever be enabled on a machine the developer
    | alone can reach. Even then only install-style verbs are offered, never
    | `run` or `exec`.
    |
    */

    'terminal' => [
        'packages' => (bool) env('WHALE_TERMINAL_PACKAGES', false),

        // A web server has a smaller PATH than the developer's shell, so each
        // tool can be pointed at directly when it is not found there.
        'binaries' => [
            'composer' => env('WHALE_COMPOSER_BINARY'),
            'npm' => env('WHALE_NPM_BINARY'),
            'pip' => env('WHALE_PIP_BINARY'),
        ],
    ],
    'auth' => [
        'required' => (bool) env('WHALE_REQUIRE_AUTH', false),
    ],
    'speech' => [

        'engine' => env('WHALE_STT_ENGINE', 'auto'), // auto, whistle, sdk

        // The needle binary and the whistle weights it loads.
        // `needle download windows-x64` then `needle download whistle`.
        'binary' => env('WHISTLE_BINARY'),
        'model' => env('WHISTLE_MODEL', 'whistle.cact'),

        'sample_rate' => 16000,
        'max_seconds' => 30,
        'timeout' => (int) env('WHISTLE_TIMEOUT', 60),

        // Pin the spoken language instead of detecting it. "auto" or blank lets
        // the model decide.
        'language' => env('WHALE_SPEECH_LANGUAGE', 'auto'),

        // Terms the decoder is biased towards, so names the user actually says
        // survive the search. Comma separated, for example "Anthropic,Laravel".
        'keywords' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('WHISTLE_KEYWORDS', '')),
        ))),
    ],

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
