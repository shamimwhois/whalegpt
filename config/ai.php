<?php

/*
|--------------------------------------------------------------------------
| Dynamic Provider Detection
|--------------------------------------------------------------------------
|
| AI_PROVIDER is optional.
|
| 1. Explicit AI_PROVIDER wins.
| 2. If only one provider is configured, use it.
| 3. If multiple providers are configured, use the first provider
|    according to the configured priority.
|
*/

$providerCredentials = [
    'anthropic' => env('ANTHROPIC_API_KEY'),
    'azure' => env('AZURE_OPENAI_API_KEY'),
    'bedrock' => env('AWS_BEARER_TOKEN_BEDROCK'),
    'cohere' => env('COHERE_API_KEY'),
    'deepseek' => env('DEEPSEEK_API_KEY'),
    'eleven' => env('ELEVENLABS_API_KEY'),
    'gemini' => env('GEMINI_API_KEY'),
    'groq' => env('GROQ_API_KEY'),
    'jina' => env('JINA_API_KEY'),
    'mistral' => env('MISTRAL_API_KEY'),

    // Ollama does not require an API key.
    // Its URL determines whether it is configured.
    'ollama' => env('OLLAMA_URL'),

    'openai' => env('OPENAI_API_KEY'),
    'openai-compatible' => env('OPENAI_COMPATIBLE_URL'),
    'openrouter' => env('OPENROUTER_API_KEY'),
    'typesafe' => env('TYPESAFE_API_KEY'),
    'voyageai' => env('VOYAGEAI_API_KEY'),
    'xai' => env('XAI_API_KEY'),
];

/*
|--------------------------------------------------------------------------
| Provider Priority
|--------------------------------------------------------------------------
*/

$providerPriority = [
    'ollama',
    'openai',
    'anthropic',
    'gemini',
    'groq',
    'deepseek',
    'mistral',
    'xai',
    'openrouter',
    'azure',
    'bedrock',
    'cohere',
    'jina',
    'voyageai',
    'typesafe',
    'eleven',
    'openai-compatible',
];

/*
|--------------------------------------------------------------------------
| Available Providers
|--------------------------------------------------------------------------
*/

$availableProviders = array_keys(
    array_filter(
        $providerCredentials,
        static fn ($value) => filled($value)
    )
);

/*
|--------------------------------------------------------------------------
| Default Provider
|--------------------------------------------------------------------------
*/

$defaultProvider = env('AI_PROVIDER');

if (
    $defaultProvider === null
    || $defaultProvider === ''
) {
    if (count($availableProviders) === 1) {
        $defaultProvider = $availableProviders[0];
    } else {
        $defaultProvider = collect($providerPriority)
            ->first(
                static fn ($provider) => in_array($provider, $availableProviders, true)
            );
    }
}

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    */

    'default' => $defaultProvider,

    /*
    |--------------------------------------------------------------------------
    | Default Provider By Operation
    |--------------------------------------------------------------------------
    |
    | Each operation can use its own provider.
    |
    */

    'default_for_images' => env(
        'AI_IMAGE_PROVIDER',
        $defaultProvider
    ),

    'default_for_audio' => env(
        'AI_AUDIO_PROVIDER',
        $defaultProvider
    ),

    'default_for_transcription' => env(
        'AI_TRANSCRIPTION_PROVIDER',
        $defaultProvider
    ),

    'default_for_embeddings' => env(
        'AI_EMBEDDING_PROVIDER',
        $defaultProvider
    ),

    'default_for_reranking' => env(
        'AI_RERANKING_PROVIDER',
        $defaultProvider
    ),

    'default_for_classification' => env(
        'AI_CLASSIFICATION_PROVIDER',
        $defaultProvider
    ),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
            'individually' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env(
                'ANTHROPIC_URL',
                'https://api.anthropic.com/v1'
            ),

            'models' => [
                'text' => [
                    'default' => env('ANTHROPIC_MODEL'),
                ],
            ],
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env(
                'AZURE_OPENAI_API_VERSION',
                '2025-04-01-preview'
            ),
            'deployment' => env(
                'AZURE_OPENAI_DEPLOYMENT',
                'gpt-4o'
            ),
            'embedding_deployment' => env(
                'AZURE_OPENAI_EMBEDDING_DEPLOYMENT',
                'text-embedding-3-small'
            ),
            'image_deployment' => env(
                'AZURE_OPENAI_IMAGE_DEPLOYMENT',
                'gpt-image-1'
            ),
            'store' => env('AZURE_OPENAI_STORE', true),

            'models' => [
                'text' => [
                    'default' => env('AZURE_OPENAI_MODEL'),
                ],
                'embeddings' => [
                    'default' => env('AZURE_OPENAI_EMBEDDING_MODEL'),
                ],
                'image' => [
                    'default' => env('AZURE_OPENAI_IMAGE_MODEL'),
                ],
            ],
        ],

        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env(
                'AWS_BEDROCK_REGION',
                'us-east-1'
            ),
            'key' => env('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => env(
                'AWS_USE_DEFAULT_CREDENTIALS',
                true
            ),

            'assume_role' => [
                'arn' => env('AWS_BEDROCK_ASSUME_ROLE_ARN'),
                'session_name' => env(
                    'AWS_BEDROCK_ASSUME_ROLE_SESSION_NAME'
                ),
                'duration_seconds' => env(
                    'AWS_BEDROCK_ASSUME_ROLE_DURATION_SECONDS'
                ),
                'external_id' => env(
                    'AWS_BEDROCK_ASSUME_ROLE_EXTERNAL_ID'
                ),
            ],
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),

            'models' => [
                'embeddings' => [
                    'default' => env('COHERE_EMBEDDING_MODEL'),
                ],
                'reranking' => [
                    'default' => env('COHERE_RERANKING_MODEL'),
                ],
            ],
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),

            'models' => [
                'text' => [
                    'default' => env('DEEPSEEK_MODEL'),
                ],
            ],
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),

            'models' => [
                'audio' => [
                    'default' => env('ELEVENLABS_MODEL'),
                ],
                'transcription' => [
                    'default' => env('ELEVENLABS_TRANSCRIPTION_MODEL'),
                ],
            ],
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env(
                'GEMINI_URL',
                'https://generativelanguage.googleapis.com/v1beta/'
            ),

            'models' => [
                'text' => [
                    'default' => env('GEMINI_MODEL'),
                ],
                'image' => [
                    'default' => env('GEMINI_IMAGE_MODEL'),
                ],
                'audio' => [
                    'default' => env('GEMINI_AUDIO_MODEL'),
                ],
                'transcription' => [
                    'default' => env('GEMINI_TRANSCRIPTION_MODEL'),
                ],
                'embeddings' => [
                    'default' => env('GEMINI_EMBEDDING_MODEL'),
                ],
            ],
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),

            'models' => [
                'text' => [
                    'default' => env('GROQ_MODEL'),
                ],
                'transcription' => [
                    'default' => env('GROQ_TRANSCRIPTION_MODEL'),
                ],
            ],
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),

            'models' => [
                'embeddings' => [
                    'default' => env('JINA_EMBEDDING_MODEL'),
                ],
                'reranking' => [
                    'default' => env('JINA_RERANKING_MODEL'),
                ],
            ],
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),

            'models' => [
                'text' => [
                    'default' => env('MISTRAL_MODEL'),
                ],
                'audio' => [
                    'default' => env('MISTRAL_AUDIO_MODEL'),
                ],
                'transcription' => [
                    'default' => env('MISTRAL_TRANSCRIPTION_MODEL'),
                ],
                'embeddings' => [
                    'default' => env('MISTRAL_EMBEDDING_MODEL'),
                ],
            ],
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY'),
            'url' => env(
                'OLLAMA_URL',
                'http://127.0.0.1:11434'
            ),

            'models' => [
                'text' => [
                    'default' => env(
                        'OLLAMA_MODEL',
                        'tinyllama:latest'
                    ),
                ],

                'embeddings' => [
                    'default' => env(
                        'OLLAMA_EMBEDDING_MODEL'
                    ),
                ],
            ],
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env(
                'OPENAI_URL',
                'https://api.openai.com/v1'
            ),
            'store' => env('OPENAI_STORE', true),

            'models' => [
                'text' => [
                    'default' => env('OPENAI_MODEL'),
                ],
                'image' => [
                    'default' => env('OPENAI_IMAGE_MODEL'),
                ],
                'audio' => [
                    'default' => env('OPENAI_AUDIO_MODEL'),
                ],
                'transcription' => [
                    'default' => env('OPENAI_TRANSCRIPTION_MODEL'),
                ],
                'embeddings' => [
                    'default' => env('OPENAI_EMBEDDING_MODEL'),
                ],
            ],
        ],

        'openai-compatible' => [
            'driver' => 'openai-compatible',
            'url' => env('OPENAI_COMPATIBLE_URL'),
            'key' => env('OPENAI_COMPATIBLE_API_KEY'),

            'models' => [
                'text' => [
                    'default' => env('OPENAI_COMPATIBLE_MODEL'),
                ],
                'embeddings' => [
                    'default' => env(
                        'OPENAI_COMPATIBLE_EMBEDDING_MODEL'
                    ),
                ],
                'transcription' => [
                    'default' => env(
                        'OPENAI_COMPATIBLE_TRANSCRIPTION_MODEL'
                    ),
                ],
            ],
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),

            'models' => [
                'text' => [
                    'default' => env('OPENROUTER_MODEL'),
                ],
                'image' => [
                    'default' => env('OPENROUTER_IMAGE_MODEL'),
                ],
                'audio' => [
                    'default' => env('OPENROUTER_AUDIO_MODEL'),
                ],
                'transcription' => [
                    'default' => env(
                        'OPENROUTER_TRANSCRIPTION_MODEL'
                    ),
                ],
                'embeddings' => [
                    'default' => env(
                        'OPENROUTER_EMBEDDING_MODEL'
                    ),
                ],
                'reranking' => [
                    'default' => env(
                        'OPENROUTER_RERANKING_MODEL'
                    ),
                ],
            ],
        ],

        'typesafe' => [
            'driver' => 'typesafe',
            'key' => env('TYPESAFE_API_KEY'),

            'models' => [
                'classification' => [
                    'default' => env(
                        'TYPESAFE_CLASSIFICATION_MODEL'
                    ),
                ],
            ],
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),

            'models' => [
                'embeddings' => [
                    'default' => env(
                        'VOYAGEAI_EMBEDDING_MODEL'
                    ),
                ],
                'reranking' => [
                    'default' => env(
                        'VOYAGEAI_RERANKING_MODEL'
                    ),
                ],
            ],
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),

            'models' => [
                'text' => [
                    'default' => env('XAI_MODEL'),
                ],
                'image' => [
                    'default' => env('XAI_IMAGE_MODEL'),
                ],
                'audio' => [
                    'default' => env('XAI_AUDIO_MODEL'),
                ],
            ],
        ],
    ],
];
