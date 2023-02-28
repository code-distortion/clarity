<?php

namespace CodeDistortion\Clarity\Tests;

use CodeDistortion\Clarity\ServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * The Laravel test case.
 */
abstract class LaravelTestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param Application $app The Laravel app.
     * @return array<int, class-string>
     */
    // phpcs:ignore
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class
        ];
    }
}
