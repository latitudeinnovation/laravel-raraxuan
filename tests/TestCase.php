<?php

namespace LatitudeInnovation\Raraxuan\Tests;

use Illuminate\Support\Facades\Http;
use LatitudeInnovation\Raraxuan\RaraxuanServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Unfaked HTTP calls fail fast instead of reaching the network.
        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [
            RaraxuanServiceProvider::class,
        ];
    }
}
