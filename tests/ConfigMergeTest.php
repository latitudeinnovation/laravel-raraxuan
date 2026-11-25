<?php

namespace LatitudeInnovation\Raraxuan\Tests;

use LatitudeInnovation\Raraxuan\RaraxuanServiceProvider;

class ConfigMergeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        // An app whose own config/raraxuan.php sets only part of the chat settings.
        $app['config']->set('raraxuan', [
            'api_key' => 'rx_app_key',
            'chat' => ['enabled' => true, 'middleware' => ['web'], 'unrelated_app_setting' => 30],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // In a real app config files load before packages register; replay that order.
        (new RaraxuanServiceProvider($this->app))->register();
    }

    public function test_package_defaults_fill_in_under_a_partial_app_config(): void
    {
        $this->assertSame('rx_app_key', config('raraxuan.api_key'));
        $this->assertTrue(config('raraxuan.chat.enabled'));
        $this->assertSame(30, config('raraxuan.chat.unrelated_app_setting'));
        $this->assertSame('/api/v1/chat', config('raraxuan.chat.api_path'));
        $this->assertSame('Assistant', config('raraxuan.chat.widget.title'));
        $this->assertSame('https://ai.raraxuan.com', config('raraxuan.base_url'));
    }

    public function test_lists_are_replaced_not_merged(): void
    {
        $this->assertSame(['web'], config('raraxuan.chat.middleware'));
        $this->assertSame(
            ['a' => ['x' => 2, 'y' => 1], 'list' => ['only']],
            RaraxuanServiceProvider::mergeDefaults(['a' => ['x' => 1, 'y' => 1], 'list' => ['one', 'two']], ['a' => ['x' => 2], 'list' => ['only']]),
        );
    }

    public function test_filament_hook_is_skipped_when_filament_is_not_installed(): void
    {
        config(['raraxuan.chat.filament' => true]);

        // Booting again must not fail without Filament.
        (new RaraxuanServiceProvider($this->app))->boot();

        $this->assertFalse(class_exists('Filament\Support\Facades\FilamentView'));
    }
}
