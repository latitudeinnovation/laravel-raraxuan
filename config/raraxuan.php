<?php

return [
    'base_url' => env('RARAXUAN_API_URL', 'https://ai.raraxuan.com'),
    'process_path' => env('RARAXUAN_PROCESS_PATH', '/api/v1/prompts/process'),
    'ping_path' => env('RARAXUAN_PING_PATH', '/api/v1/ping'),
    'api_key' => env('RARAXUAN_API_KEY'),
    'timeout' => env('RARAXUAN_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Chat
    |--------------------------------------------------------------------------
    |
    | The chat box is a JavaScript widget loaded from Raraxuan. It talks only to
    | the routes below in your own app, which add your API key on the server and
    | identify the signed-in user. Your API key never reaches the browser.
    |
    */
    'chat' => [
        // Registers the chat routes and renders <x-raraxuan::chat />. Off by default.
        'enabled' => (bool) env('RARAXUAN_CHAT_ENABLED', false),

        // Raraxuan Chat API base path.
        'api_path' => env('RARAXUAN_CHAT_PATH', '/api/v1/chat'),

        // The hosted widget script.
        'widget_url' => env('RARAXUAN_CHAT_WIDGET_URL', 'https://ai.raraxuan.com/chat/v1/raraxuan-chat.js'),

        // Optional prompt template (slug) whose system prompt and model the chat uses.
        'template' => env('RARAXUAN_CHAT_TEMPLATE'),

        // Fixed template variables. Per-user values: Raraxuan::chatVariablesUsing(...).
        'variables' => [],

        // Your app's routes for the widget.
        'route_prefix' => env('RARAXUAN_CHAT_ROUTE_PREFIX', 'raraxuan/chat'),

        // "web" gives session auth and CSRF protection; "auth" blocks guests.
        'middleware' => ['web', 'auth'],

        // Optional Gate ability the user must pass, e.g. "use-chat".
        'gate' => null,

        // Messages per minute per user, on top of your API key's limit at Raraxuan.
        'messages_per_minute' => (int) env('RARAXUAN_CHAT_MESSAGES_PER_MINUTE', 20),

        // Show the chat in every Filament panel automatically (needs a signed-in user).
        'filament' => (bool) env('RARAXUAN_CHAT_FILAMENT', false),

        // Seconds to wait for a reply.
        'timeout' => (int) env('RARAXUAN_CHAT_TIMEOUT', 120),

        // Defaults for <x-raraxuan::chat />; each can be overridden as an attribute.
        'widget' => [
            'title' => env('RARAXUAN_CHAT_TITLE', 'Assistant'),
            'welcome' => null,
            'placeholder' => null,
            'theme' => 'light', // light, dark or auto
            'position' => 'bottom-right', // bottom-right or bottom-left
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP (optional)
    |--------------------------------------------------------------------------
    |
    | Lets the chat call tools in your app through your own MCP server (laravel/mcp).
    | Protect that route with the "raraxuan.mcp" middleware and give Raraxuan the
    | same token. Only tools you write and register are exposed.
    |
    */
    'mcp' => [
        'token' => env('RARAXUAN_MCP_TOKEN'),

        // Guard whose user provider resolves the chatting user. Null = default guard.
        'guard' => env('RARAXUAN_MCP_GUARD'),
    ],
];
