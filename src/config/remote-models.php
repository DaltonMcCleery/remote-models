<?php

return [
    'domain' => null,

    'cache-path' => null,

    'cache-prefix' => 'remote',

    'cache-ttl' => null,

    'api-path' => '/api/_remote/_models',

    'api-key' => env('REMOTE_MODELS_API_KEY', null),

    'host_models' => [
        // \App\Models\User::class,
    ],
];
