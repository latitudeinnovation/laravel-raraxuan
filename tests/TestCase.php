<?php

namespace LatitudeInnovation\Raraxuan\Tests;

use LatitudeInnovation\Raraxuan\RaraxuanServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            RaraxuanServiceProvider::class,
        ];
    }
}
