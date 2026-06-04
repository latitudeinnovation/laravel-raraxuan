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
        $this->assertSame('https://ai.raraxuan.com/api', config('raraxuan.base_url'));
        $this->assertSame(60, config('raraxuan.timeout'));
    }
}
