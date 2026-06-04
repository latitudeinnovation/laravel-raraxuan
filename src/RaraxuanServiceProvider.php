<?php

namespace LatitudeInnovation\Raraxuan;

use Illuminate\Support\ServiceProvider;

class RaraxuanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/raraxuan.php', 'raraxuan');

        $this->app->singleton(RaraxuanClient::class, function () {
            return new RaraxuanClient();
        });

        $this->app->alias(RaraxuanClient::class, 'raraxuan');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/raraxuan.php' => config_path('raraxuan.php'),
        ], 'raraxuan-config');
    }
}
