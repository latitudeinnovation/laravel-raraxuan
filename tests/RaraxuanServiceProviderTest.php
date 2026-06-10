<?php

namespace LatitudeInnovation\Raraxuan\Tests;

use LatitudeInnovation\Raraxuan\RaraxuanClient;

class RaraxuanServiceProviderTest extends TestCase
{
    public function test_it_registers_the_client_binding(): void
    {
        $this->assertInstanceOf(RaraxuanClient::class, $this->app->make(RaraxuanClient::class));
        $this->assertInstanceOf(RaraxuanClient::class, $this->app->make('raraxuan'));
    }

    public function test_it_merges_default_config(): void
    {
        $this->assertSame('https://ai.raraxuan.com', config('raraxuan.base_url'));
        $this->assertSame('/v1/prompts/process', config('raraxuan.process_path'));
        $this->assertSame('/v1/ping', config('raraxuan.ping_path'));
        $this->assertSame(60, config('raraxuan.timeout'));
    }
}
