<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documentation
    |--------------------------------------------------------------------------
    |
    | The pages offered under /docs, in sidebar order. Each entry names the
    | content partial that renders it, so a new guide is one entry here plus a
    | matching view. `group` is the sidebar heading a page files under.
    |
    | DocsController 404s any slug that is not a key in this map, so the route
    | can never render an empty shell for a page that does not exist.
    |
    */

    'pages' => [
        'introduction' => [
            'title' => 'Introduction',
            'group' => 'Getting started',
            'summary' => 'What Whale AI is and how the pieces fit together.',
        ],
        'authentication' => [
            'title' => 'Authentication',
            'group' => 'Getting started',
            'summary' => 'Sign in with Google, GitHub, or an email and password.',
        ],
        'chat-completions' => [
            'title' => 'Chat completions',
            'group' => 'API guides',
            'summary' => 'Send a message and stream the assistant\'s reply.',
        ],
        'models' => [
            'title' => 'Models',
            'group' => 'API guides',
            'summary' => 'List the providers and models this install can run.',
        ],
        'errors' => [
            'title' => 'Errors',
            'group' => 'Reference',
            'summary' => 'The status codes the API returns and what each one means.',
        ],
    ],

];
