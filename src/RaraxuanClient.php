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
            ->post($this->endpoint('/v1/run'), $payload)
            ->throw()
            ->json();
    }

    public function agent(string $agent, array $input = []): array
    {
        return $this->run([
            'agent' => $agent,
            'input' => $input,
        ]);
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
