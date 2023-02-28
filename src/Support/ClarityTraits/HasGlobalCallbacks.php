<?php

namespace CodeDistortion\Clarity\Support\ClarityTraits;

use CodeDistortion\Clarity\Settings;
use CodeDistortion\Clarity\Support\Common;

/**
 * Methods to store and retrieve "global" callbacks (that always run when an exception occurs).
 */
trait HasGlobalCallbacks
{
    use HasFramework;



    /**
     * Add a "global" callback, to always run when an exception occurs.
     *
     * @param callable $callback The callback to run.
     * @return void
     */
    public static function globalCallback(callable $callback): void
    {
        self::globalCallbacks($callback);
    }

    /**
     * Add "global" callbacks, to always run when an exception occurs.
     *
     * @param callable|callable[] $callbacks     The callback/s to run.
     * @param callable|callable[] ...$callbacks2 The callback/s to run.
     * @return void
     */
    public static function globalCallbacks(callable|array $callbacks, callable|array ...$callbacks2): void
    {
        /** @var callable[] $callbacks */
        $callbacks = Common::normaliseArgs(self::getGlobalCallbacks(), func_get_args());

        self::xSetGlobalCallbacks($callbacks);
    }

    /**
     * Get the "global" callbacks from global storage.
     *
     * @return callable[]
     */
    private static function getGlobalCallbacks(): mixed
    {
        /** @var callable[] $return */
        $return = self::resolveDepInjection()->get(Settings::CONTAINER_KEY_GLOBAL_CALLBACKS, []);
        return $return;
    }

    /**
     * Set the "global" callbacks in global storage.
     *
     * @param callable[] $callbacks The global callbacks to store.
     * @return void
     */
    private static function xSetGlobalCallbacks(array $callbacks): void
    {
        self::resolveDepInjection()->set(Settings::CONTAINER_KEY_GLOBAL_CALLBACKS, $callbacks);
    }
}
