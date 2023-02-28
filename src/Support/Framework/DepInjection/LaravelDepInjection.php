<?php

namespace CodeDistortion\Clarity\Support\Framework\DepInjection;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;

/**
 * Use Laravel to manage dependency injection.
 */
class LaravelDepInjection implements FrameworkDepInjectionInterface
{
    /**
     * Get a value (or class instance) using the dependency container.
     *
     * @param string $key     The key to retrieve.
     * @param mixed  $default The default value to fall back to (will be executed when callable).
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        /** @var Application $app */
        $app = app();

        try {

            return $app->make($key);

        } catch (BindingResolutionException) {
        }

        return is_callable($default)
            ? $app->call($default)
            : $default;
    }

    /**
     * Get a value (or class instance) using the dependency container. Will store the default when not present.
     *
     * @param string $key     The key to retrieve.
     * @param mixed  $default The default value to fall back to (will be executed when callable).
     * @return mixed
     */
    public static function getOrSet(string $key, mixed $default): mixed
    {
        /** @var Application $app */
        $app = app();

        try {

            $return = $app->make($key);

            if (!is_null($return)) {
                return $return;
            }

        } catch (BindingResolutionException) {
        }

        $return = is_callable($default)
            ? $app->call($default)
            : $default;

        self::set($key, $return);

        return $return;
    }

    /**
     * Store a value or class instance in the dependency container.
     *
     * @param string $key   The key to set.
     * @param mixed  $value The value to set.
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        /** @var Application $app */
        $app = app();

        method_exists($app, 'scoped')
            ? $app->scoped($key, fn() => $value)
            : $app->singleton($key, fn() => $value);
    }

    /**
     * Run a callable, resolving parameters first using the dependency container.
     *
     * @param callable $callable   The callable to run.
     * @param mixed[]  $parameters The parameters to pass.
     * @return mixed
     */
    public static function call(callable $callable, array $parameters = []): mixed
    {
        /** @var Application $app */
        $app = app();
        return $app->call($callable, $parameters);
    }
}
