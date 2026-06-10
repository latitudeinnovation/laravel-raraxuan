<?php

namespace LatitudeInnovation\Raraxuan;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
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
        $processPath = config('raraxuan.process_path', '/v1/prompts/process');

        if (! is_string($processPath) || trim($processPath) === '') {
            throw InvalidConfigurationException::missingProcessPath();
        }

        return trim($processPath);
    }

    protected function pingPath(): string
    {
        $pingPath = config('raraxuan.ping_path', '/v1/ping');

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
