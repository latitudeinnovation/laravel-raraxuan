<?php

namespace LatitudeInnovation\Raraxuan\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LatitudeInnovation\Raraxuan\Exceptions\InvalidConfigurationException;
use LatitudeInnovation\Raraxuan\Exceptions\MissingApiKeyException;
use LatitudeInnovation\Raraxuan\RaraxuanClient;

class RaraxuanClientTest extends TestCase
{
    public function test_it_calls_an_agent(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test/api/',
            'raraxuan.timeout' => 30,
        ]);

        Http::fake([
            'https://ai.raraxuan.test/api/v1/run' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->app->make(RaraxuanClient::class)->agent('seo-writer', [
            'topic' => 'Laravel hosting',
        ]);

        $this->assertSame(['ok' => true], $response);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://ai.raraxuan.test/api/v1/run'
                && $request->hasHeader('Authorization', 'Bearer rx_test_key')
                && $request['agent'] === 'seo-writer'
                && $request['input'] === ['topic' => 'Laravel hosting'];
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
