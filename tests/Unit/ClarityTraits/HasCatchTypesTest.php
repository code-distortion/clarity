<?php

namespace CodeDistortion\Clarity\Tests\Unit\ClarityTraits;

use CodeDistortion\Clarity\Clarity;
use CodeDistortion\Clarity\Exceptions\ClarityInitialisationException;
use CodeDistortion\Clarity\Settings;
use CodeDistortion\Clarity\Support\ClarityTraits\HasFramework;
use CodeDistortion\Clarity\Tests\LaravelTestCase;
use Exception;

/**
 * Test the HasCatchTypes trait.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class HasCatchTypesTest extends LaravelTestCase
{
    use HasFramework;



    /**
     * Test calling of summary() and context() methods before other initialisation methods.
     *
     * @test
     * @dataProvider initialisationMethodsDataProvider
     *
     * @param string  $method The initialisation method to call.
     * @param mixed[] $args   The arguments to pass.
     * @return void
     * @throws Exception When the clarity method cannot be called.
     */
    public static function test_calling_summary_and_context_before_other_initialisation_methods(
        string $method,
        array $args,
    ): void {

        self::run_methods_on_clarity('summary', ['summary'], $method, $args);
        self::run_methods_on_clarity('context', [['context']], $method, $args);
    }

    /**
     * Run a static Clarity method (that's not prime() or run()), then run another method, and test that an exception
     * was thrown.
     *
     * @param string  $method1 The static method to call first.
     * @param mixed[] $args1   The arguments to use for the static method call.
     * @param string  $method2 The next method to call.
     * @param mixed[] $args2   The arguments to use for the next method call.
     * @return void
     * @throws Exception When $method2 can't be called.
     */
    private static function run_methods_on_clarity(string $method1, array $args1, string $method2, array $args2): void
    {
        $exceptionWasThrown = false;

        $clarity = is_callable($toCall = [Clarity::class, $method1])
            ? call_user_func_array($toCall, $args1)
            : throw new Exception("Can't call method $method1 on class clarity");

        try {

            is_callable($toCall = [$clarity, $method2])
                ? call_user_func_array($toCall, $args2)
                : throw new Exception("Can't call method $method2 on class clarity");

        } catch (ClarityInitialisationException) {
            $exceptionWasThrown = true;
        }
        self::assertTrue($exceptionWasThrown);
    }

    /**
     * DataProvider for test_calling_summary_and_context_before_other_initialisation_methods.
     *
     * @return array<int, array<int|string, array<int, callable|string>|string>>
     */
    public static function initialisationMethodsDataProvider(): array
    {
        return [
            ['method' => 'catch', [Exception::class]],
            ['method' => 'match', ['abc']],
            ['method' => 'matchRegex', ['/^abc/']],
            ['method' => 'callback', [fn() => 'a']],
            ['method' => 'callbacks', [fn() => 'a']],
            ['method' => 'known', ['abc']],
            ['method' => 'channel', ['abc']],
            ['method' => 'channels', ['abc']],
            ['method' => 'level', [Settings::REPORTING_LEVEL_WARNING]],
            ['method' => 'debug', []],
            ['method' => 'info', []],
            ['method' => 'notice', []],
            ['method' => 'warning', []],
            ['method' => 'error', []],
            ['method' => 'critical', []],
            ['method' => 'alert', []],
            ['method' => 'emergency', []],
            ['method' => 'report', []],
            ['method' => 'dontReport', []],
            ['method' => 'rethrow', []],
            ['method' => 'dontRethrow', []],
            ['method' => 'default', ['abc']],
        ];
    }
}
