<?php

namespace CodeDistortion\Clarity\Tests\Unit\ClarityTraits;

use CodeDistortion\Clarity\CatchType;
use CodeDistortion\Clarity\Support\ClarityTraits\HasFramework;
use CodeDistortion\Clarity\Tests\LaravelTestCase;

/**
 * Test the HasFramework trait.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class HasFrameworkTest extends LaravelTestCase
{
    use HasFramework;



    /**
     * Test the dependency injection functionality.
     *
     * @test
     *
     * @return void
     */
    public static function test_dependency_injection(): void
    {
        $depInjection = self::resolveDepInjection();

        $callable = fn() => 'called-default';
        $callable2 = fn() => 'called-default2';

        // get
        $key = 'get-key';
        // value not stored yet
        self::assertNull($depInjection->get($key));
        // return the default
        self::assertSame('default', $depInjection->get($key, 'default'));
        // the default value was not stored
        self::assertNull($depInjection->get($key)); // the default value was not stored

        $key = 'get-key-with-callable';
        // called and return the default
        self::assertSame('called-default', $depInjection->get($key, $callable));
        // the default value was not stored
        self::assertNull($depInjection->get($key));

        // getOrSet
        $key = 'getOrSet-key';
        // set, and return the default
        self::assertSame('default', $depInjection->getOrSet($key, 'default'));
        // the default was stored
        self::assertSame('default', $depInjection->get($key));
        // already stored
        self::assertSame('default', $depInjection->getOrSet($key, 'default2'));

        $key = 'getOrSet-key-with-callable';
        // call, set, and return the default
        self::assertSame('called-default', $depInjection->getOrSet($key, $callable));
        // the default was stored
        self::assertSame('called-default', $depInjection->get($key));
        // already stored
        self::assertSame('called-default', $depInjection->getOrSet($key, $callable2));

        // set
        $key = 'set-key';
        // set the value
        $depInjection->set($key, 'default');
        // already stored
        self::assertSame('default', $depInjection->get($key));
        // already stored
        self::assertSame('default', $depInjection->getOrSet($key, 'default2'));

        $key = 'set-key-with-callable';
        // set the value - the callable is not run
        $depInjection->set($key, $callable);
        // already stored
        self::assertSame($callable, $depInjection->get($key));

        // call
        $callableRan = false;
        $callable = function (CatchType $catchType, string $blah) use (&$callableRan) {
            self::assertInstanceOf(CatchType::class, $catchType);
            self::assertSame('hello', $blah);
            $callableRan = true;
        };
        $depInjection->call($callable, ['blah' => 'hello']);
        self::assertTrue($callableRan);
    }
}
