# Laravel Raraxuan

Laravel SDK for calling the Raraxuan AI API from Laravel applications.

## Requirements

- PHP 8.2+
- Laravel 12 or 13

## Installation

```bash
composer require latitudeinnovation/laravel-raraxuan
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=raraxuan-config
```

## Configuration

Add these values to your Laravel project's `.env` file:

```dotenv
RARAXUAN_API_URL=https://ai.raraxuan.com
RARAXUAN_API_KEY=rx_live_xxxxxxxxx
RARAXUAN_TIMEOUT=60
```

The published config file is `config/raraxuan.php`:

```php
return [
    'base_url' => env('RARAXUAN_API_URL', 'https://ai.raraxuan.com'),
    'process_path' => env('RARAXUAN_PROCESS_PATH', '/api/v1/prompts/process'),
    'ping_path' => env('RARAXUAN_PING_PATH', '/api/v1/ping'),
    'api_key' => env('RARAXUAN_API_KEY'),
    'timeout' => env('RARAXUAN_TIMEOUT', 60),
    'chat' => [
        // see "Chat" below
    ],
];
```

## Usage

Process a prompt through the facade:

```php
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

$response = Raraxuan::processPrompt('customer-support-reply', [
    'customer_message' => 'Hello',
    'tone' => 'friendly',
    'product' => 'Acme',
]);
```

Check API health:

```php
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

$response = Raraxuan::ping();
```

## Chat

Add an AI chat box to any Laravel app. The chat box is a small JavaScript widget hosted by Raraxuan. It talks only to routes inside your app, which add your API key on the server and identify the signed-in user. Your API key never reaches the browser, and each user only sees their own conversations.

### 1. Enable it

```dotenv
RARAXUAN_API_KEY=your-api-key
RARAXUAN_CHAT_ENABLED=true

# Optional
RARAXUAN_CHAT_TEMPLATE=support-bot   # a Raraxuan prompt template: its system prompt and model are used
RARAXUAN_CHAT_TITLE="Support"
```

The API key needs the `chat` ability (a key with no abilities set can use everything).

### 2. Add the widget

Put the component in your layout, for example just before `</body>`:

```blade
<x-raraxuan::chat />
```

It renders only for signed-in users, loads the script once per page, and accepts `title`, `welcome`, `placeholder`, `theme` (`light`, `dark`, `auto`), `position` (`bottom-right`, `bottom-left`) and `open`.

**Filament** (any version): register a render hook in a service provider. No Filament-specific package is needed.

```php
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

FilamentView::registerRenderHook(
    PanelsRenderHook::BODY_END,
    fn (): string => Blade::render('<x-raraxuan::chat />'),
);
```

**Any other page:** load the script yourself and point the widget at the routes below.

```html
<script src="https://ai.raraxuan.com/chat/v1/raraxuan-chat.js" defer></script>
<raraxuan-chat endpoint="/raraxuan/chat" csrf-token="{{ csrf_token() }}"></raraxuan-chat>
```

### Routes and security

When enabled, the package registers `/raraxuan/chat/*` behind the `web` and `auth` middleware (session login and CSRF protection). Guests get a 401. Change this in `config/raraxuan.php`:

```php
'chat' => [
    'route_prefix' => 'raraxuan/chat',
    'middleware' => ['web', 'auth'],
    'gate' => 'use-chat',          // optional Gate ability
    'messages_per_minute' => 20,   // per user
    'variables' => ['company' => 'Acme'], // fixed template variables
],
```

The browser cannot choose the template, the variables or the user: they come from your server. By default the user is identified by `$user->getAuthIdentifier()`. To change that, or to pass per-user template variables:

```php
use Illuminate\Contracts\Auth\Authenticatable;
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

// In a service provider's boot() method:
Raraxuan::resolveChatUserUsing(fn (Authenticatable $user) => 'team-'.$user->team_id.':'.$user->id);
Raraxuan::chatVariablesUsing(fn (Authenticatable $user) => ['customer_name' => $user->name]);
```

Errors a visitor can act on (too many messages, budget reached, invalid input) are shown to them. Configuration and AI provider errors show a generic message and are written to your log with the error code. Message content is never logged.

### Let the chat use your app (MCP, optional)

Chat works without this. Add it when the assistant should look things up or do things in your app, such as finding an order or cancelling it. You decide exactly which actions exist: nothing in your app is exposed unless you write a tool for it.

**1. Write tools with [laravel/mcp](https://github.com/laravel/mcp)** and authorize inside each one, as you would in a controller. `$request->user()` is the user who is chatting.

```php
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Find one of the signed-in customer\'s orders by number.')]
#[IsReadOnly] // read-only tools run without asking; all others need the user's approval
class LookupOrder extends Tool
{
    public function handle(Request $request): Response
    {
        $order = $request->user()->orders()->where('number', $request->get('number'))->first();

        return $order ? Response::text("Order {$order->number}: {$order->status}") : Response::error('Order not found.');
    }

    public function schema(JsonSchema $schema): array
    {
        return ['number' => $schema->string()->required()];
    }
}
```

**2. Expose them on a route protected by `raraxuan.mcp`** (for example in `routes/ai.php`):

```php
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/raraxuan', \App\Mcp\Servers\AppServer::class)->middleware('raraxuan.mcp');
```

```dotenv
RARAXUAN_MCP_TOKEN=a-long-random-secret
```

The middleware rejects requests without this secret and signs in the user the chat is acting for (looked up by the same id the chat uses). If you customized `resolveChatUserUsing()`, map ids back too:

```php
Raraxuan::resolveMcpUserUsing(fn (string $id) => User::find(str($id)->afterLast(':')));
```

**3. Connect it in Raraxuan.** Ask your Raraxuan admin to add an MCP server with your public HTTPS URL (for example `https://app.example.com/mcp/raraxuan`) and the same secret. *Test connection* lists your tools and shows which ones ask the user first.

How it behaves:

- Raraxuan calls your MCP server from its servers. The browser never sees the secret or talks to your MCP route.
- Read-only tools run straight away. Every other tool shows the user an **Approve / Deny** card with the exact arguments, and runs only after they approve. Admins can adjust this per tool.
- Each tool call is logged in Raraxuan (tool, arguments, result, duration, who approved). Calls are limited per reply, and tool output is treated as data, not instructions.
- Approve or deny from PHP: `Raraxuan::chat()->as($user)->conversation($id)->approve($callId)` (or `->deny($callId)`). Pending requests are in a reply's `approvals` list.

### Chat from PHP

```php
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

$reply = Raraxuan::chat()->message('Hello')->send();           // as the signed-in user
$reply['message']['content'];

$next = Raraxuan::chat()
    ->as($user)                                                  // or any string id
    ->conversation($reply['conversation_id'])
    ->message('Tell me more')
    ->send();

Raraxuan::chat()->as($user)->conversation($id)->message('Hi')->stream(function (string $delta) {
    echo $delta;                                                 // pieces of the reply as they arrive
});

Raraxuan::chat()->as($user)->conversations();                    // recent conversations
Raraxuan::chat()->as($user)->conversation($id)->get();          // one conversation with messages
Raraxuan::chat()->as($user)->conversation($id)->delete();
```

Chat API errors throw `LatitudeInnovation\Raraxuan\Exceptions\RaraxuanApiException` with `errorCode` (for example `rate_limited`, `budget_exceeded`, `conversation_not_found`) and the HTTP `status`.

## Artisan Commands

After installing and configuring the SDK, you can test the API from Artisan.

Run an agent/template with a JSON variables object:

```bash
php artisan raraxuan:run customer-support-reply --input='{"customer_message":"Hello","tone":"friendly","product":"Acme"}'
```

Check API health:

```bash
php artisan raraxuan:ping
```

If the API returns an HTTP error, the commands print the status code and response body instead of a stack trace.

The API returns wrapped JSON payloads, which the SDK returns unchanged:

```php
[
    'success' => true,
    'data' => [
        // ...
    ],
]

[
    'success' => false,
    'error' => [
        'code' => '...',
        'message' => '...',
    ],
]
```

## Error Handling

The SDK throws its own exceptions for missing or invalid local configuration:

- `LatitudeInnovation\Raraxuan\Exceptions\MissingApiKeyException`
- `LatitudeInnovation\Raraxuan\Exceptions\InvalidConfigurationException`

API request failures use Laravel's HTTP client and call `throw()` on failed responses. Catch `Illuminate\Http\Client\RequestException` when you need to handle API errors.

```php
use Illuminate\Http\Client\RequestException;
use LatitudeInnovation\Raraxuan\Exceptions\RaraxuanException;
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

try {
    $response = Raraxuan::processPrompt('customer-support-reply', [
        'customer_message' => 'Hello',
        'tone' => 'friendly',
        'product' => 'Acme',
    ]);
} catch (RequestException $exception) {
    report($exception);

    $status = $exception->response->status();
    $error = $exception->response->json();
} catch (RaraxuanException $exception) {
    report($exception);
}
```

## Testing

After installing development dependencies, run:

```bash
composer test
```

## Contributing & Releases

- `main` is the only permanent branch. Do not create `staging` or `develop` branches.
- Create temporary branches for your work, such as `feature/my-change` or `fix/my-bug`.
- Open pull requests against `main`. CI (`composer validate` and the test matrix) must pass before merging.
- Stable releases are published by tagging `main` with a Semantic Versioning tag (`v0.2.1` for a fix, `v0.3.0` for new backward-compatible features). Packagist picks up the tags.
- `dev-main` stays available for anyone who needs the latest unreleased code.

## Local Development

To try local changes, require this package from a local Laravel project:

```bash
composer config repositories.raraxuan path ../laravel-raraxuan
composer require latitudeinnovation/laravel-raraxuan:@dev
```

If Composer cannot detect the package version, require `dev-main` from a Git checkout.

## Queue Support

Queue and job helpers are not included yet. A future version can add jobs for long-running agent calls while keeping this client as the synchronous API layer.
