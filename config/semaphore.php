<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Semaphore SMS API Configuration
    |--------------------------------------------------------------------------
    | Sign up at https://semaphore.co to get your API key.
    | Set SEMAPHORE_API_KEY and SEMAPHORE_SENDER_NAME in your .env file.
    */

    'api_key'     => env('SEMAPHORE_API_KEY', ''),
    'sender_name' => env('SEMAPHORE_SENDER_NAME', 'SCHOOLCARE'),

];
