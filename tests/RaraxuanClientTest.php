<?php

namespace LatitudeInnovation\Raraxuan\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LatitudeInnovation\Raraxuan\Exceptions\InvalidConfigurationException;
use LatitudeInnovation\Raraxuan\Exceptions\MissingApiKeyException;
use LatitudeInnovation\Raraxuan\RaraxuanClient;

class RaraxuanClientTest extends TestCase
{
    public function test_it_processes_a_prompt(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test/',
            'raraxuan.timeout' => 30,
        ]);

        Http::fake([
            'https://ai.raraxuan.test/v1/prompts/process' => Http::response([
                'success' => true,
                'data' => [
                    'reply' => 'Hello',
                ],
            ], 200),
        ]);

        $response = $this->app->make(RaraxuanClient::class)->processPrompt('customer-support-reply', [
            'customer_message' => 'Hello',
            'tone' => 'friendly',
            'product' => 'Acme',
        ]);

        $this->assertSame([
            'success' => true,
            'data' => [
                'reply' => 'Hello',
            ],
        ], $response);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://ai.raraxuan.test/v1/prompts/process'
                && $request->hasHeader('Authorization', 'Bearer rx_test_key')
                && $request['template'] === 'customer-support-reply'
                && $request['variables'] === [
                    'customer_message' => 'Hello',
                    'tone' => 'friendly',
                    'product' => 'Acme',
                ];
        });
    }

    public function test_it_pings_the_api(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test',
        ]);

        Http::fake([
            'https://ai.raraxuan.test/v1/ping' => Http::response([
                'success' => true,
                'data' => [
                    'status' => 'ok',
                ],
            ], 200),
        ]);

        $response = $this->app->make(RaraxuanClient::class)->ping();

        $this->assertSame([
            'success' => true,
            'data' => [
                'status' => 'ok',
            ],
        ], $response);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ai.raraxuan.test/v1/ping'
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer rx_test_key'));
    }

    public function test_it_returns_api_error_payloads_unchanged_when_http_request_succeeds(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test',
        ]);

        Http::fake([
            'https://ai.raraxuan.test/v1/prompts/process' => Http::response([
                'success' => false,
                'error' => [
                    'code' => 'invalid_template',
                    'message' => 'Template not found.',
                ],
            ], 200),
        ]);

        $response = $this->app->make(RaraxuanClient::class)->processPrompt('missing-template');

        $this->assertSame([
            'success' => false,
            'error' => [
                'code' => 'invalid_template',
                'message' => 'Template not found.',
            ],
        ], $response);
    }

    public function test_it_allows_the_official_paths_to_be_configured(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test/api',
            'raraxuan.process_path' => '/custom/prompts/process',
            'raraxuan.ping_path' => '/custom/ping',
        ]);

        Http::fake([
            'https://ai.raraxuan.test/api/custom/prompts/process' => Http::response(['success' => true], 200),
            'https://ai.raraxuan.test/api/custom/ping' => Http::response(['success' => true], 200),
        ]);

        $client = $this->app->make(RaraxuanClient::class);

        $this->assertSame(['success' => true], $client->processPrompt('seo-writer'));
        $this->assertSame(['success' => true], $client->ping());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ai.raraxuan.test/api/custom/prompts/process');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ai.raraxuan.test/api/custom/ping');
    }

    public function test_it_maps_agent_calls_to_prompt_processing_for_compatibility(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test',
        ]);

        Http::fake([
            'https://ai.raraxuan.test/v1/prompts/process' => Http::response(['success' => true], 200),
        ]);

        $response = $this->app->make(RaraxuanClient::class)->agent('seo-writer', [
            'topic' => 'Laravel hosting',
        ]);

        $this->assertSame(['success' => true], $response);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://ai.raraxuan.test/v1/prompts/process'
                && $request['template'] === 'seo-writer'
                && $request['variables'] === ['topic' => 'Laravel hosting'];
        });
    }

    public function test_it_requires_an_api_key(): void
    {
        config(['raraxuan.api_key' => null]);

        $this->expectException(MissingApiKeyException::class);

        $this->app->make(RaraxuanClient::class)->agent('seo-writer');
    }

    public function test_it_requires_a_valid_timeout(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.timeout' => 0,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        $this->app->make(RaraxuanClient::class)->agent('seo-writer');
    }
}
