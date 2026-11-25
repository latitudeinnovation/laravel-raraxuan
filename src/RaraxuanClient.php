<?php

namespace LatitudeInnovation\Raraxuan;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LatitudeInnovation\Raraxuan\Chat\ChatApi;
use LatitudeInnovation\Raraxuan\Chat\ChatContext;
use LatitudeInnovation\Raraxuan\Chat\PendingChat;
use LatitudeInnovation\Raraxuan\Exceptions\InvalidConfigurationException;
use LatitudeInnovation\Raraxuan\Exceptions\MissingApiKeyException;

class RaraxuanClient
{
    protected function http(): PendingRequest
    {
        return Http::withToken($this->apiKey())
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout());
    }

    public function run(array $payload): array
    {
        return $this->http()
            ->post($this->endpoint($this->processPath()), $payload)
            ->throw()
            ->json();
    }

    public function processPrompt(string $template, array $variables = []): array
    {
        return $this->run([
            'template' => $template,
            'variables' => $variables,
        ]);
    }

    public function ping(): array
    {
        return $this->http()
            ->get($this->endpoint($this->pingPath()))
            ->throw()
            ->json();
    }

    public function agent(string $agent, array $input = []): array
    {
        return $this->processPrompt($agent, $input);
    }

    /**
     * Start a chat request, e.g. Raraxuan::chat()->message('Hello')->send().
     */
    public function chat(): PendingChat
    {
        return new PendingChat(app(ChatApi::class), app(ChatContext::class));
    }

    /**
     * Customize the end-user id sent to Raraxuan (default: the user's auth identifier).
     *
     * @param  Closure(Authenticatable): (string|int)  $resolver
     */
    public function resolveChatUserUsing(Closure $resolver): void
    {
        app(ChatContext::class)->resolveUserUsing($resolver);
    }

    /**
     * Per-user template variables for new conversations, merged over config raraxuan.chat.variables.
     *
     * @param  Closure(Authenticatable): array<string, mixed>  $resolver
     */
    public function chatVariablesUsing(Closure $resolver): void
    {
        app(ChatContext::class)->variablesUsing($resolver);
    }

    /**
     * Map an end-user id from Raraxuan back to your user, for MCP tool calls.
     * Needed when you customize resolveChatUserUsing().
     *
     * @param  Closure(string): ?Authenticatable  $resolver
     */
    public function resolveMcpUserUsing(Closure $resolver): void
    {
        app(ChatContext::class)->resolveMcpUserUsing($resolver);
    }

    protected function endpoint(string $path): string
    {
        return $this->baseUrl() . '/' . ltrim($path, '/');
    }

    protected function baseUrl(): string
    {
        $baseUrl = config('raraxuan.base_url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw InvalidConfigurationException::missingBaseUrl();
        }

        return rtrim($baseUrl, '/');
    }

    protected function processPath(): string
    {
        $processPath = config('raraxuan.process_path', '/api/v1/prompts/process');

        if (! is_string($processPath) || trim($processPath) === '') {
            throw InvalidConfigurationException::missingProcessPath();
        }

        return trim($processPath);
    }

    protected function pingPath(): string
    {
        $pingPath = config('raraxuan.ping_path', '/api/v1/ping');

        if (! is_string($pingPath) || trim($pingPath) === '') {
            throw InvalidConfigurationException::missingPingPath();
        }

        return trim($pingPath);
    }

    protected function apiKey(): string
    {
        $apiKey = config('raraxuan.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw MissingApiKeyException::missing();
        }

        return trim($apiKey);
    }

    protected function timeout(): int
    {
        $timeout = (int) config('raraxuan.timeout', 60);

        if ($timeout <= 0) {
            throw InvalidConfigurationException::invalidTimeout();
        }

        return $timeout;
    }
}
