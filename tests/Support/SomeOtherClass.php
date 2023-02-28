<?php

namespace CodeDistortion\Clarity\Tests\Support;

use CodeDistortion\Clarity\Clarity;
use Exception;

/**
 * A class and method that runs a closure, to be called by other tests.
 */
class SomeOtherClass
{
    /** @var string The message to use when throwing exceptions. */
    private static string $exceptionMessage = 'Something happened';



    /**
     * Get Clarity to run a callback, and rethrow the exception.
     *
     * @return void
     * @throws Exception Will always throw.
     */
    public static function triggerAndRethrow(): void
    {
        Clarity::prime(fn() => throw new Exception(self::$exceptionMessage))
            ->summary(__CLASS__)
            ->context([__CLASS__])
            ->rethrow()
            ->execute();
    }
}
