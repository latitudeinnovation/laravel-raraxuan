<?php

namespace LatitudeInnovation\Raraxuan;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use LatitudeInnovation\Raraxuan\Chat\ChatApi;
use LatitudeInnovation\Raraxuan\Chat\ChatContext;
use LatitudeInnovation\Raraxuan\Http\Middleware\AuthenticateRaraxuanMcp;
use LatitudeInnovation\Raraxuan\Console\Commands\PingRaraxuan;
use LatitudeInnovation\Raraxuan\Console\Commands\RunRaraxuanAgent;

class RaraxuanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigDeeply(__DIR__ . '/../config/raraxuan.php', 'raraxuan');

        $this->app->singleton(RaraxuanClient::class, function () {
            return new RaraxuanClient();
        });

        $this->app->alias(RaraxuanClient::class, 'raraxuan');

        $this->app->singleton(ChatApi::class);
        $this->app->singleton(ChatContext::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/raraxuan.php' => config_path('raraxuan.php'),
        ], 'raraxuan-config');

        $this->app['router']->aliasMiddleware('raraxuan.mcp', AuthenticateRaraxuanMcp::class);

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'raraxuan');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/raraxuan'),
        ], 'raraxuan-views');

        if (config('raraxuan.chat.enabled') && ! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/chat.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                PingRaraxuan::class,
                RunRaraxuanAgent::class,
            ]);
        }

        $this->registerFilamentWidget();
    }

    /**
     * With RARAXUAN_CHAT_FILAMENT=true, show the chat in every Filament panel (any version),
     * without making Filament a dependency of this package.
     */
    protected function registerFilamentWidget(): void
    {
        $filamentView = 'Filament\\Support\\Facades\\FilamentView';
        $renderHook = 'Filament\\View\\PanelsRenderHook';

        if (! config('raraxuan.chat.enabled') || ! config('raraxuan.chat.filament') || ! class_exists($filamentView) || ! class_exists($renderHook)) {
            return;
        }

        $filamentView::registerRenderHook(
            constant($renderHook . '::BODY_END'),
            fn (): string => Blade::render('<x-raraxuan::chat />'),
        );
    }

    /**
     * Like mergeConfigFrom(), but nested: the app's config/raraxuan.php may set only some
     * keys (or be an unrelated file with the same name) and still get this package's
     * defaults underneath. Lists, such as middleware, are replaced as a whole.
     */
    protected function mergeConfigDeeply(string $path, string $key): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $config->set($key, self::mergeDefaults(require $path, (array) $config->get($key, [])));
    }

    /**
     * @param  array<array-key, mixed>  $defaults
     * @param  array<array-key, mixed>  $overrides
     * @return array<array-key, mixed>
     */
    public static function mergeDefaults(array $defaults, array $overrides): array
    {
        foreach ($overrides as $name => $value) {
            $default = $defaults[$name] ?? null;

            $defaults[$name] = is_array($value) && is_array($default) && ! array_is_list($value) && ! array_is_list($default)
                ? self::mergeDefaults($default, $value)
                : $value;
        }

        return $defaults;
    }
}
