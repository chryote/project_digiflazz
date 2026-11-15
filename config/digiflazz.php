<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Digiflazz Merchant Credentials
    |--------------------------------------------------------------------------
    |
    | Read strictly from environment variables without default fallbacks.
    |
    */

    'username' => env('DIGIFLAZZ_USERNAME'),

    'development_key' => env('DIGIFLAZZ_DEVELOPMENT_KEY', env('DEVELOPMENT_KEY')),

    'api_key' => env('DIGIFLAZZ_DEVELOPMENT_KEY', env('DEVELOPMENT_KEY')),
];
