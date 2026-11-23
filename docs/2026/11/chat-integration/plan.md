# Codex Implementation Plan: Laravel Raraxuan Chat SDK

## Objective

Extend the existing `latitudeinnovation/laravel-raraxuan` Composer package to provide secure, simple integration between Laravel applications and the Raraxuan AI Chat platform.

The SDK must allow developers to add an AI Chat Box to Laravel or Filament applications with minimal setup.

It must remain independent of Filament and its version-specific APIs.

The chat interface will be loaded from `ai.raraxuan.com`; do not duplicate or bundle the Chat Widget source code into this repository.

## Phase 1: Repository Investigation

Before implementation:

1. Inspect the existing Composer package structure.
2. Review supported PHP and Laravel versions.
3. Identify existing API clients, authentication, service providers, facades, configuration, and tests.
4. Inspect the existing Raraxuan API integration.
5. Reuse existing HTTP clients and error handling.

Preserve backward compatibility with existing consumers.

Do not rewrite the current SDK unnecessarily.

## Phase 2: Chat API Client

Extend the existing SDK with Chat API capabilities.

Support:

- Creating conversations
- Listing conversations
- Retrieving conversation history
- Sending chat messages
- Streaming responses
- Deleting conversations
- Structured error handling
- Configurable request timeouts

Proposed developer API:

```php
$response = Raraxuan::chat()
    ->message('Hello')
    ->send();
```

For existing conversations:

```php
$response = Raraxuan::chat()
    ->conversation($conversationId)
    ->message('Summarize my documents')
    ->send();
```

These methods are proposed interfaces; align their final signatures with existing SDK conventions.

Maintain compatibility with the Raraxuan `/api/v1/chat` contract.

## Phase 3: Laravel Configuration

Extend the existing package configuration rather than creating unnecessary separate configuration files.

Proposed `.env` values:

```dotenv
RARAXUAN_API_KEY=your-api-key
RARAXUAN_CHAT_ENABLED=true
RARAXUAN_CHAT_WIDGET_URL=https://ai.raraxuan.com/chat/v1/raraxuan-chat.js
```

Use existing API base URL configuration where available.

Provide sensible defaults and allow environment-specific overrides.

All API credentials must remain server-side.

## Phase 4: Secure Chat Proxy

Provide optional package routes that allow the JavaScript Chat Widget to communicate securely with Raraxuan.

Proposed route prefix:

```text
/raraxuan/chat
```

Responsibilities:

- Authenticate the current Laravel user.
- Authorize access to the chat feature.
- Apply rate limits.
- Prevent cross-user conversation access.
- Forward validated requests to Raraxuan using the server-side API key.
- Relay streaming responses.
- Return consistent errors.

Do not allow anonymous chat by default.

Do not use a browser-supplied user ID or organization ID as trusted authorization information.

Provide configurable middleware, route prefix, and feature enablement.

Ensure routes are not registered when the feature is disabled.

Use Laravel's session authentication and CSRF protections for browser requests.

## Phase 5: Chat Widget Integration

Provide a simple way to render the remotely hosted JavaScript widget.

Proposed Blade integration:

```blade
<x-raraxuan::chat />
```

The Blade component should generate the required script and custom element markup.

Equivalent output:

```html
<script
    src="https://ai.raraxuan.com/chat/v1/raraxuan-chat.js"
    defer>
</script>

<raraxuan-chat
    endpoint="/raraxuan/chat">
</raraxuan-chat>
```

Support configurable widget attributes, including:

- Chat title
- Theme
- Position
- Endpoint
- Enabled/disabled state

Avoid loading duplicate script elements when multiple components appear on a page.

Do not expose Raraxuan API credentials through widget attributes.

## Phase 6: Filament Compatibility Without Filament Dependency

The package must remain independent of:

- Filament v3
- Filament v4
- Filament v5
- Future Filament releases

Do not add Filament as a Composer requirement.

Support Filament applications through standard Laravel integration points.

For example, developers may load the widget through a Filament render hook or an application-owned Blade layout.

Provide documentation showing the appropriate integration methods.

Do not introduce separate chat components for every Filament version.

The JS Web Component must be responsible for the Chat UI, not Filament.

## Phase 7: Optional MCP Support

Support Laravel MCP integration without requiring every client to enable it.

The SDK should not automatically expose models, tables, or application functions.

Where useful, provide reusable configuration helpers for:

- Registering MCP endpoints
- Associating an application with its Raraxuan MCP connection
- Establishing authenticated tool execution
- Passing an authorized end-user context
- Handling tool execution errors

If MCP functionality is better handled directly by Laravel's official MCP package, document that integration rather than duplicating it inside the SDK.

Do not invent a proprietary MCP protocol.

Important:

- MCP is optional.
- Every MCP tool must be explicitly registered.
- Authorization must be enforced on every tool invocation.
- Organization and user scope must be preserved.
- Sensitive write actions must require explicit approval.
- Do not expose private routes or databases automatically.

## Phase 8: Error Handling and Observability

Integrate with Laravel logging and the package's existing conventions.

Support:

- API authentication failures
- Network failures
- Request timeouts
- Rate-limit responses
- Streaming interruptions
- Invalid conversation IDs
- MCP connection errors
- Structured exceptions

Never log API secrets or sensitive message content by default.

## Phase 9: Tests and Compatibility

Test against the Laravel and PHP versions officially supported by this SDK.

Include tests for:

- API client behavior
- Authentication configuration
- Chat proxy middleware
- CSRF protection
- Authorization failures
- Conversation ownership
- Streaming proxy behavior
- Widget rendering
- Disabled-feature behavior
- Missing API keys
- Retry and error handling

Run Composer validation, package tests, and code-style checks.

Avoid adding Filament as a required dependency merely for compatibility testing.

## Phase 10: Documentation

Update the README with a minimal setup guide.

Target developer experience:

### Step 1: Install

```bash
composer require latitudeinnovation/laravel-raraxuan
```

### Step 2: Configure

```dotenv
RARAXUAN_API_KEY=your-api-key
RARAXUAN_CHAT_ENABLED=true
```

### Step 3: Add Chat Widget

```blade
<x-raraxuan::chat />
```

### Step 4: Optional MCP

Explain how developers can expose approved Laravel MCP tools and securely connect their MCP server to Raraxuan.

Make clear that:

- Chat works without MCP.
- MCP is only required for AI access to application functions/data.
- Tools must be explicitly implemented and authorized.
- The application must expose a securely reachable MCP endpoint or use another approved connection mechanism.

Document both standard Laravel and Filament usage.

## Implementation Rules

- Keep this package lightweight.
- Do not depend on Filament.
- Do not build a separate JavaScript chat interface.
- Do not duplicate Raraxuan AI execution logic.
- Preserve existing SDK capabilities.
- Follow existing package naming conventions.
- Maintain backward compatibility.
- Avoid unnecessary Composer dependencies.
- Use existing Laravel conventions.
- Add tests for new functionality.

## Definition of Done

The implementation is successful when:

1. Existing SDK features still work.
2. Laravel developers can send chat requests through the SDK.
3. The Chat Widget can be embedded using a Blade component.
4. The SDK securely proxies browser chat requests.
5. The widget loads from `ai.raraxuan.com`.
6. Laravel users cannot access unauthorized conversations.
7. Chat works without Filament and without MCP.
8. Optional MCP integrations work with explicitly authorized tools.

Start by inspecting the existing SDK architecture and reviewing the Raraxuan Chat API contract. Implement the integration incrementally and validate with automated tests.