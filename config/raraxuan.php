<?php

return [
    'base_url' => env('RARAXUAN_API_URL', 'https://ai.raraxuan.com'),
    'process_path' => env('RARAXUAN_PROCESS_PATH', '/v1/prompts/process'),
    'ping_path' => env('RARAXUAN_PING_PATH', '/v1/ping'),
    'api_key' => env('RARAXUAN_API_KEY'),
    'timeout' => env('RARAXUAN_TIMEOUT', 60),
];
