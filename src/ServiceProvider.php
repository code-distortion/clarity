<?php

namespace CodeDistortion\Clarity;

use CodeDistortion\Clarity\Support\Context;
use CodeDistortion\Clarity\Support\ContextInterface;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

/**
 * Clarity's Laravel ServiceProvider.
 */
class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Service-provider register method.
     *
     * @return void
     */
    public function register(): void
    {
        $this->initialiseConfig();

        $this->app->bind(ContextInterface::class, fn() => Clarity::getContext());
        $this->app->bind(Context::class, fn() => Clarity::getContext());
    }

    /**
     * Service-provider boot method.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishConfig();
    }



    /**
     * Initialise the config settings file.
     *
     * @return void
     */
    private function initialiseConfig(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/..' . Settings::LARAVEL_REAL_CONFIG, Settings::LARAVEL_CONFIG_NAME);
    }

    /**
     * Allow the default config to be published.
     *
     * @return void
     */
    private function publishConfig(): void
    {
        $src = __DIR__ . '/..' . Settings::LARAVEL_PUBLISHABLE_CONFIG;
        $dest = config_path(Settings::LARAVEL_CONFIG_NAME . '.php');

        $this->publishes([$src => $dest], 'config');
    }
}
