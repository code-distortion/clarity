<?php

namespace CodeDistortion\Clarity\Support\ClarityTraits;

use CodeDistortion\Clarity\Exceptions\ClarityInitialisationException;
use CodeDistortion\Clarity\Support\Environment;
use CodeDistortion\Clarity\Support\Framework\Config\FrameworkConfigInterface;
use CodeDistortion\Clarity\Support\Framework\Config\LaravelFrameworkConfig;
use CodeDistortion\Clarity\Support\Framework\DepInjection\FrameworkDepInjectionInterface;
use CodeDistortion\Clarity\Support\Framework\DepInjection\LaravelDepInjection;

/**
 * Method to resolve the config class to use.
 */
trait HasFramework
{
    /** @var FrameworkConfigInterface|null A cache of the object used to interact with the framework's configuration. */
    private static ?FrameworkConfigInterface $frameworkConfig = null;

    /** @var FrameworkDepInjectionInterface|null A cache of the dependency injection object. */
    private static ?FrameworkDepInjectionInterface $frameworkDepInjection = null;



    /**
     * Resolve which config instance to use.
     *
     * @return FrameworkConfigInterface
     * @throws ClarityInitialisationException When the current framework can't be determined.
     */
    private static function resolveFrameworkConfig(): FrameworkConfigInterface
    {
        return self::$frameworkConfig ??= self::xBuildNewFrameworkConfig();
    }

    /**
     * Build a new config instance.
     *
     * @return FrameworkConfigInterface
     * @throws ClarityInitialisationException When the current framework can't be determined.
     */
    private static function xBuildNewFrameworkConfig(): FrameworkConfigInterface
    {
        if (Environment::isLaravel()) {
            return new LaravelFrameworkConfig();
        }
        throw ClarityInitialisationException::unknownFramework();
    }



    /**
     * Resolve which dependency injection instance to use.
     *
     * @return FrameworkDepInjectionInterface
     * @throws ClarityInitialisationException When the current framework can't be determined.
     */
    private static function resolveDepInjection(): FrameworkDepInjectionInterface
    {
        return self::$frameworkDepInjection ??= self::xBuildNewDepInjection();
    }

    /**
     * Build a new dependency injection instance.
     *
     * @return FrameworkDepInjectionInterface
     * @throws ClarityInitialisationException When the current framework can't be determined.
     */
    private static function xBuildNewDepInjection(): FrameworkDepInjectionInterface
    {
        if (Environment::isLaravel()) {
            return new LaravelDepInjection();
        }
        throw ClarityInitialisationException::unknownFramework();
    }
}
