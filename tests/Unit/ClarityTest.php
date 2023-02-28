<?php

namespace CodeDistortion\Clarity\Tests\Unit;

use CodeDistortion\Clarity\CatchType;
use CodeDistortion\Clarity\Clarity;
use CodeDistortion\Clarity\Exceptions\ClarityInitialisationException;
use CodeDistortion\Clarity\Settings;
use CodeDistortion\Clarity\Support\ClarityTraits\HasFramework;
use CodeDistortion\Clarity\Support\Context;
use CodeDistortion\Clarity\Support\Context\CallStack\Frame;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\CallMeta;
use CodeDistortion\Clarity\Support\Environment;
use CodeDistortion\Clarity\Support\Inspector;
use CodeDistortion\Clarity\Tests\LaravelTestCase;
use CodeDistortion\Clarity\Tests\Support\MethodCall;
use CodeDistortion\Clarity\Tests\Support\MethodCalls;
use DivisionByZeroError;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Test the Clarity class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ClarityTest extends LaravelTestCase
{
    use HasFramework;



    /** @var string The message to use when throwing exceptions. */
    private static string $exceptionMessage = 'Something happened';

    /** @var string|null The exception to be triggered (if any). Put here to reduce the space taken when passing. */
    private static ?string $currentExceptionToTrigger;



    /**
     * Test that Clarity operates properly with the given combinations of ways it can be called.
     *
     * @test
     * @dataProvider clarityMethodCallsDataProvider
     *
     * @param MethodCalls                 $initMethodCalls               Methods to call when initialising the Clarity
     *                                                                   object.
     * @param class-string|null           $exceptionToTrigger            The exception type to trigger (if any).
     * @param boolean|string|integer|null $callbackReturnValue           The value that callbacks will return.
     * @param boolean                     $expectExceptionUponInit       Expect exception thrown when initialising.
     * @param boolean                     $expectCallbackToBeRun         Except the exception callback to be run?.
     * @param boolean                     $expectExceptionToBeLogged     Expect the exception to be logged?.
     * @param boolean                     $expectExceptionThrownToCaller Except the exception to be thrown to the
     *                                                                   caller?.
     * @return void
     * @throws Exception When a method doesn't exist when instantiating the Clarity class.
     */
    public static function test_that_clarity_method_calls_operate_properly(
        MethodCalls $initMethodCalls,
        ?string $exceptionToTrigger,
        bool|string|int|null $callbackReturnValue,
        bool $expectExceptionUponInit,
        bool $expectCallbackToBeRun,
        bool $expectExceptionToBeLogged,
        bool $expectExceptionThrownToCaller,
    ): void {

        // set up the closure to run
        $intendedReturnValue = mt_rand();
        $closureRunCount = 0;
        $closure = function () use (&$closureRunCount, $intendedReturnValue, $exceptionToTrigger) {
            $closureRunCount++;
            if (!is_null($exceptionToTrigger)) {
                /** @var Throwable $exception */
                $exception = new $exceptionToTrigger(self::$exceptionMessage);
                throw $exception;
            }
            return $intendedReturnValue;
        };



        $exceptionCallbackWasRun = false;
        $exceptionCallbackRunCount = [];
        $exceptionCallbackCount = 0;



        // initialise the Clarity object
        $clarity = Clarity::prime($closure, $exception);
        $exceptionWasThrownUponInit = false;
        try {
            foreach ($initMethodCalls->getCalls() as $methodCall) {

                $method = $methodCall->getMethod();
                $args = $methodCall->getArgs();

                // place the exception callback into the args for calls to callback() / callbacks()
                foreach ($args as $index => $arg) {
                    if ((in_array($method, ['callback', 'callbacks'])) && ($arg ?? null)) {

                        $exceptionCallbackRunCount[$exceptionCallbackCount] = 0;

                        $args[$index] = function () use (
                            &$exceptionCallbackWasRun,
                            &$exceptionCallbackRunCount,
                            $exceptionCallbackCount,
                            $callbackReturnValue
                        ) {
                            $exceptionCallbackWasRun = true;
                            $exceptionCallbackRunCount[$exceptionCallbackCount]++;
                            return $callbackReturnValue;
                        };

                        $exceptionCallbackCount++;
                    }
                }

                $toCall = [$clarity, $method];
                if (is_callable($toCall)) {
                    /** @var Clarity $clarity */
                    $clarity = call_user_func_array($toCall, $args);
                } else {
                    throw new Exception("Can't call method $method on class Clarity");
                }
            }
        } catch (Throwable $e) {
//            dump("Exception: \"{$e->getMessage()}\" in {$e->getFile()}:{$e->getLine()}");
            $exceptionWasThrownUponInit = true;
        }

        self::assertSame($expectExceptionUponInit, $exceptionWasThrownUponInit);
        if ($exceptionWasThrownUponInit) {
            return;
        }



        // Note: the actual level used is handled by the app/Exceptions/Handler.php
        // in Laravel, it's logged as error unless updated
        $expectExceptionToBeLogged
            ? self::logShouldReceive(Settings::REPORTING_LEVEL_ERROR)
            : self::logShouldNotReceive(Settings::REPORTING_LEVEL_ERROR);



        // run the closure
        $exceptionWasDetectedOutside = false;
        $returnValue = null;
        try {
            $returnValue = $clarity->execute();
        } catch (Throwable $e) {
//            dump("Exception: \"{$e->getMessage()}\" in {$e->getFile()}:{$e->getLine()}");
            $exceptionWasDetectedOutside = true;
        }



        self::assertSame(1, $closureRunCount);

        self::assertSame($expectCallbackToBeRun, $exceptionCallbackWasRun);
        for ($count = 0; $count < $exceptionCallbackCount; $count++) {
            $expected = $expectCallbackToBeRun
                ? 1
                : 0;
            self::assertSame($expected, $exceptionCallbackRunCount[$count]);
        }

        self::assertSame($expectExceptionThrownToCaller, $exceptionWasDetectedOutside);

        $expectedReturn = is_null($exceptionToTrigger)
            ? $intendedReturnValue
            : null;
        self::assertSame($expectedReturn, $returnValue);

        if ($exceptionToTrigger) {
            self::assertInstanceOf($exceptionToTrigger, $exception);
        } else {
            self::assertNull($exception);
        }
    }





    /**
     * DataProvider for test_that_clarity_method_calls_operate_properly.
     *
     * Provide the different combinations of how the Clarity object can be set up and called.
     *
     * @return array<integer, array<string, mixed>>
     */
    public static function clarityMethodCallsDataProvider(): array
    {
        $summaryCombinations = [
            null, // don't call
            ['doing something'],
        ];

        $contextCombinations = [
            null, // don't call
            ['id1' => 1, 'id2' => 2],
        ];

        $catchCombinations = [
            null, // don't call
            [Throwable::class],
            [InvalidArgumentException::class],
            [DivisionByZeroError::class],
//            [[Throwable::class, DivisionByZeroError::class]],
//            [[Throwable::class, InvalidArgumentException::class]],
//            [Throwable::class, DivisionByZeroError::class],
//            [Throwable::class, InvalidArgumentException::class],
        ];

        $matchCombinations = [
            null, // don't call
            [self::$exceptionMessage],
            ['(NO MATCH)'],
        ];

        $matchRegexCombinations = [
            null, // don't call
            ['/Something/'],
            ['(NO MATCH)'],
        ];

        $callbackCombinations = [
            null, // don't call
            [true], // is replaced with the callback later, inside the test
        ];

        $knownCombinations = [
            null, // don't call
            ['ABC'],
//            [['ABC', 'DEF']],
        ];

        $channelCombinations = [
            null, // don't call
            ['stack'],
        ];

        $levelCombinations = [
            null, // don't call
            ['info'],
//            ['BLAH'], // error
        ];

        $reportCombinations = [
            null, // don't call
            [], // called with no arguments
        ];

        $rethrowCombinations = [
            null, // don't call
            [], // called with no arguments
        ];

        $triggerExceptionTypes = [
            null, // don't throw an exception
            Exception::class,
            InvalidArgumentException::class,
        ];


        $return = [];

        foreach ($triggerExceptionTypes as $exceptionToTrigger) {

            self::$currentExceptionToTrigger = $exceptionToTrigger;

//          foreach ($summaryCombinations as $summary) {
//          foreach ($contextCombinations as $context) {
//          foreach ($channelCombinations as $channel) {
//          foreach ($levelCombinations as $level) {
//          foreach ($knownCombinations as $known) {
            foreach ($catchCombinations as $catch) {
                foreach ($matchCombinations as $match) {
                    foreach ($matchRegexCombinations as $matchRegex) {
                        foreach ($callbackCombinations as $callback) {
                            foreach ($reportCombinations as $report) {
                                foreach ($rethrowCombinations as $rethrow) {

                                    $initMethodCalls = MethodCalls::new()
//                                        ->add('summary', $summary)
//                                        ->add('context', $context)
                                        ->add('catch', $catch)
                                        ->add('match', $match)
                                        ->add('matchRegex', $matchRegex)
                                        ->add('callback', $callback)
//                                        ->add('known', $known)
//                                        ->add('channel', $channel)
//                                        ->add('level', $level)
                                        ->add('report', $report)
//                                        ->addCall('dontReport', $dontReport)
                                        ->add('rethrow', $rethrow)
//                                        ->addCall('dontRethrow', $dontRethrow)
//                                        ->addCall('execute', $execute)
                                    ;

                                    $return[] = self::buildParams($initMethodCalls);
                                }
                            }
                        }
                    }
                }
            }



            $return[] = self::buildParams(MethodCalls::add('summary')); // error - no params
            $return[] = self::buildParams(MethodCalls::add('summary', ['doing something']));

            $return[] = self::buildParams(MethodCalls::add('context')); // error - no params
            $return[] = self::buildParams(MethodCalls::add('context', [['id' => 1]]));

            $return[] = self::buildParams(MethodCalls
                ::add('summary', ['doing something'])
                ->add('context', [['id' => 1]]));



            $return[] = self::buildParams(MethodCalls::add('catch')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('catch', [Exception::class])
                ->add('catch', [Throwable::class, DivisionByZeroError::class])
                ->add('catch', [[Exception::class, DivisionByZeroError::class]]));



            $return[] = self::buildParams(MethodCalls::add('match')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('match', ['Blah1'])
                ->add('match', ['Blah2', 'Blah3'])
                ->add('match', [['Blah2', 'Blah4']]));

            $return[] = self::buildParams(MethodCalls::add('matchRegex')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('matchRegex', ['/^Blah1$/'])
                ->add('matchRegex', ['/^Blah2$/', '/^Blah3$/'])
                ->add('matchRegex', [['/^Blah2$/', '/^Blah4$/']]));



            $return[] = self::buildParams(MethodCalls::add('callback')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('callback', [true])
                ->add('callback', [true])
                ->add('callback', [true]));
            $return[] = self::buildParams(MethodCalls::add('callbacks')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('callbacks', [true])
                ->add('callbacks', [true, true])
                ->add('callbacks', [[true, true]])
                ->add('callback', [[true]]));

            foreach ([true, false] as $report) {
                foreach ([true, false] as $rethrow) {

                    $methodCalls = MethodCalls::add('callback', [true])
                        ->add('report', [$report])
                        ->add('rethrow', [$rethrow]);

                    $return[] = self::buildParams($methodCalls);
                    $return[] = self::buildParams($methodCalls, false);
                    $return[] = self::buildParams($methodCalls, 'hello');
                    $return[] = self::buildParams($methodCalls, 'hello');
                    $return[] = self::buildParams($methodCalls, 123);
                    $return[] = self::buildParams($methodCalls, null);
                }
            }



            $return[] = self::buildParams(MethodCalls::add('known')); // error - no params
            $return[] = self::buildParams(MethodCalls::add('known', ['ABC']));
            $return[] = self::buildParams(MethodCalls::add('known', ['ABC', 'DEF']));
            $return[] = self::buildParams(MethodCalls::add('known', [['ABC', 'DEF']]));
            $return[] = self::buildParams(MethodCalls::add('known', [['ABC', 'DEF'], 'GHI']));
            $return[] = self::buildParams(MethodCalls
                ::add('known', ['ABC',])
                ->add('known', ['DEF'])
                ->add('known', ['ABC']));
            $return[] = self::buildParams(MethodCalls
                ::add('known', ['ABC',])
                ->add('known', ['DEF', 'GHI'])
                ->add('known', [['JKL', 'GHI']])
                ->add('known', [['JKL', 'GHI'], 'MNO']));



            $return[] = self::buildParams(MethodCalls::add('channel')); // error - no params
            $return[] = self::buildParams(MethodCalls::add('channel', ['a']));
            $return[] = self::buildParams(MethodCalls::add('channels', ['a']));
            $return[] = self::buildParams(MethodCalls::add('channels', ['a', 'b']));
            $return[] = self::buildParams(MethodCalls::add('channels', [['a', 'b']]));
            $return[] = self::buildParams(MethodCalls::add('channels', [['a', 'b'], 'c']));
            $return[] = self::buildParams(MethodCalls
                ::add('channel', ['a'])
                ->add('channel', ['b'])
                ->add('channel', ['a']));
            $return[] = self::buildParams(MethodCalls::add('channels')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('channels', ['a'])
                ->add('channels', ['b', 'c'])
                ->add('channels', [['d', 'c']])
                ->add('channels', [['d', 'c'], 'z']));



            $return[] = self::buildParams(MethodCalls::add('level')); // error - no params
            $return[] = self::buildParams(MethodCalls::add('level', ['BLAH'])); // error - invalid level
            $return[] = self::buildParams(MethodCalls
                ::add('level', [Settings::REPORTING_LEVEL_INFO])
                ->add('level', [Settings::REPORTING_LEVEL_WARNING])
                ->add('level', [Settings::REPORTING_LEVEL_INFO]));
            foreach (Settings::LOG_LEVELS as $level) {
                $return[] = self::buildParams(MethodCalls::add('level', [$level]));
            }



            $return[] = self::buildParams(MethodCalls::add('report'));
            $return[] = self::buildParams(MethodCalls::add('report', [true]));
            $return[] = self::buildParams(MethodCalls::add('report', [false]));
            $return[] = self::buildParams(MethodCalls::add('dontReport'));
            $return[] = self::buildParams(MethodCalls::add('report')->add('dontReport'));
            $return[] = self::buildParams(MethodCalls::add('report')->add('dontReport')->add('report'));



            $return[] = self::buildParams(MethodCalls::add('rethrow'));
            $return[] = self::buildParams(MethodCalls::add('rethrow', [true]));
            $return[] = self::buildParams(MethodCalls::add('rethrow', [false]));
            $return[] = self::buildParams(MethodCalls::add('dontRethrow'));
            $return[] = self::buildParams(MethodCalls::add('rethrow')->add('dontRethrow'));
            $return[] = self::buildParams(MethodCalls::add('rethrow')->add('dontRethrow')->add('rethrow'));



            // test more catch combinations
            $possibleCatchArgs = [
                Throwable::class,
                InvalidArgumentException::class,
                DivisionByZeroError::class,
                new CatchType(),
                CatchType::catch(Throwable::class),
                CatchType::catch(DivisionByZeroError::class),
            ];

            $catchCombinations2 = [];
            $catchCombinations2[] = null; // don't call
            foreach ($possibleCatchArgs as $catchArg1) {
                $catchCombinations2[] = [$catchArg1];
                foreach ($possibleCatchArgs as $catchArg2) {
                    if ($catchArg1 !== $catchArg2) {
                        $catchCombinations2[] = [$catchArg1, $catchArg2];
                    }
                }
            }

            foreach ($catchCombinations2 as $catch) {
                foreach ($matchCombinations as $match) {
                    $initMethodCalls = MethodCalls::add('catch', $catch)->add('match', $match);
                    $return[] = self::buildParams($initMethodCalls);
                }
            }
        }

        return $return;
    }

    /**
     * Determine the parameters to pass to the test_that_clarity_context_settings_operate_properly test.
     *
     * @param MethodCalls                 $initMethodCalls     Methods to call when initialising the Clarity object.
     * @param boolean|string|integer|null $callbackReturnValue The value that callbacks will return.
     * @return array<string, mixed>
     */
    private static function buildParams(
        MethodCalls $initMethodCalls,
        bool|string|int|null $callbackReturnValue = true
    ): array {

        $expectExceptionUponInit = self::willExceptionBeThrownUponInit($initMethodCalls);
        $fallbackCalls = self::buildFallbackCalls($initMethodCalls);

        $willBeCaughtBy = null;
        if (!$expectExceptionUponInit) {
            $catchTypes = self::pickCatchTypes($initMethodCalls);
            $willBeCaughtBy = self::determineWhatWillCatchTheException(
                self::$currentExceptionToTrigger,
                $fallbackCalls,
                $catchTypes
            );
        }

        return [
            'initMethodCalls' => $initMethodCalls,
            'exceptionToTrigger' => self::$currentExceptionToTrigger,
            'callbackReturnValue' => $callbackReturnValue,
            'expectExceptionUponInit' => $expectExceptionUponInit,
            'expectCallbackToBeRun' => self::determineIfCallbacksWillBeRun(
                $willBeCaughtBy,
                $initMethodCalls
            ),
            'expectExceptionToBeLogged' => self::determineIfExceptionWillBeLogged(
                $willBeCaughtBy,
                $fallbackCalls,
                $callbackReturnValue
            ),
            'expectExceptionThrownToCaller' => self::willExceptionBeThrownToCaller(
                self::$currentExceptionToTrigger,
                $willBeCaughtBy,
                $fallbackCalls,
                $callbackReturnValue
            ),
        ];
    }

    /**
     * Build the method calls that build the fallback object.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the Clarity object.
     * @return MethodCalls
     */
    private static function buildFallbackCalls(MethodCalls $initMethodCalls): MethodCalls
    {

        $fallbackCalls = new MethodCalls();
        foreach ($initMethodCalls->getCalls() as $methodCall) {

            if ($methodCall->getMethod() == 'catch') {

                $args = $methodCall->getArgsFlat(fn($a) => !$a instanceof CatchType);
                if (count($args)) {
                    $fallbackCalls->add($methodCall->getMethod(), $args);
                }

            } else {
                $fallbackCalls->add($methodCall->getMethod(), $methodCall->getArgs());
            }
        }

        return $fallbackCalls;
    }

    /**
     * Pick the already built CatchType objects from the initialisation calls.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the Clarity object.
     * @return CatchType[]
     */
    private static function pickCatchTypes(MethodCalls $initMethodCalls): array
    {
        /** @var CatchType[] $args */
        $args = $initMethodCalls->getAllCallArgsFlat('catch', fn($arg) => $arg instanceof CatchType);
        return $args;
    }



    /**
     * Determine if a thrown exception will be caught.
     *
     * @param string|null $exceptionToTrigger The exception type to trigger (if any).
     * @param MethodCalls $fallbackCalls      The method calls that build the fallback object.
     * @param CatchType[] $catchTypes         The already built CatchType objects from the initialisation calls.
     * @return CatchType|MethodCalls|null
     */
    private static function determineWhatWillCatchTheException(
        ?string $exceptionToTrigger,
        MethodCalls $fallbackCalls,
        array $catchTypes
    ): CatchType|MethodCalls|null {

        if (is_null($exceptionToTrigger)) {
            return null;
        }



        // check each CatchType first
        foreach ($catchTypes as $catchType) {
            if (self::wouldCatchTypeCatch($exceptionToTrigger, $catchType, $fallbackCalls)) {
                return $catchType;
            }
        }

        // if there are CatchTypes, and the fall-back doesn't define class/es to catch, then stop
        if ((count($catchTypes)) && (!$fallbackCalls->hasCall('catch'))) {
            return null;
        }

        // check the fallback settings second
        /** @var string[] $fallbackCatchClasses */
        $fallbackCatchClasses = $fallbackCalls->getAllCallArgsFlat('catch');
        if (!self::checkIfExceptionClassesMatch($exceptionToTrigger, $fallbackCatchClasses)) {
            return null;
        }
        /** @var string[] $fallbackMatchStrings */
        $fallbackMatchStrings = $fallbackCalls->getAllCallArgsFlat('match');
        /** @var string[] $fallbackMatchRegexes */
        $fallbackMatchRegexes = $fallbackCalls->getAllCallArgsFlat('matchRegex');
        $a = self::checkIfMatchesMatch($fallbackMatchStrings);
        $b = self::checkIfRegexesMatch($fallbackMatchRegexes);

        if (($a === false || $b === false) && $a !== true && $b !== true) {
            return null;
        }
        return $fallbackCalls;
    }

    /**
     * Check if a given CatchType would catch an exception.
     *
     * @param string      $exceptionToTrigger The exception type to trigger (if any).
     * @param CatchType   $catchType          The CatchType to check.
     * @param MethodCalls $fallbackCalls      The method calls that build the fallback object.
     * @return boolean
     */
    private static function wouldCatchTypeCatch(
        string $exceptionToTrigger,
        CatchType $catchType,
        MethodCalls $fallbackCalls
    ): bool {

        $inspector = new Inspector($catchType);

        if (!self::checkIfExceptionClassesMatch($exceptionToTrigger, $inspector->getExceptionClasses())) {
            return false;
        }

        /** @var string[] $fallbackMatchStrings */
        $fallbackMatchStrings = $fallbackCalls->getAllCallArgsFlat('match');
        /** @var string[] $fallbackMatchRegexes */
        $fallbackMatchRegexes = $fallbackCalls->getAllCallArgsFlat('matchRegex');

        $matchStrings = $inspector->getRawMatchStrings() ?: $fallbackMatchStrings;
        $matchRegexes = $inspector->getRawMatchRegexes() ?: $fallbackMatchRegexes;

        $a = self::checkIfMatchesMatch($matchStrings);
        $b = self::checkIfRegexesMatch($matchRegexes);
        if (($a === false || $b === false) && $a !== true && $b !== true) {
            return false;
        }

        return true;
    }

    /**
     * Check if an array of exception classes match the exception type.
     *
     * @param string   $exceptionToTrigger The exception type that will be triggered.
     * @param string[] $exceptionClasses   The exception types to catch.
     * @return boolean
     */
    private static function checkIfExceptionClassesMatch(string $exceptionToTrigger, array $exceptionClasses): bool
    {
        if (!count($exceptionClasses)) {
            return true; // implies that all exceptions should be caught
        }
        if (in_array(Throwable::class, $exceptionClasses)) {
            return true;
        }
        if (in_array($exceptionToTrigger, $exceptionClasses)) {
            return true;
        }
        return false;
    }

    /**
     * Check if an array of match strings would match the exception message.
     *
     * @param string[] $matchStrings The matches to try.
     * @return boolean|null
     */
    private static function checkIfMatchesMatch(array $matchStrings): ?bool
    {
        if (!count($matchStrings)) {
            return null;
        }

        return in_array(self::$exceptionMessage, $matchStrings);
    }

    /**
     * Check if an array of regexes would match the exception message.
     *
     * @param string[] $matchRegexes The matches to try.
     * @return boolean|null
     */
    private static function checkIfRegexesMatch(array $matchRegexes): ?bool
    {
        if (!count($matchRegexes)) {
            return null;
        }

        foreach ($matchRegexes as $regex) {
            if (preg_match($regex, self::$exceptionMessage)) {
                return true;
            }
        }
        return false;
    }



    /**
     * Determine if an exception will be triggered when setting up the Clarity instance.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the Clarity object.
     * @return boolean
     */
    private static function willExceptionBeThrownUponInit(MethodCalls $initMethodCalls): bool
    {
        $methodsAllowedToHaveNoParameters = [
            'report',
            'dontReport',
            'rethrow',
            'dontRethrow',
            'execute',
        ];

        // check the "level" arguments
        $fallbackLevels = collect($initMethodCalls->getCalls('level'))
            ->map(fn(MethodCall $m) => $m->getArgsFlat())
            ->flatten() // todo check this
            ->toArray();
        /** @var string|null $lastFallbackLevel */
        $lastFallbackLevel = collect($fallbackLevels)->last();

        /** @var CatchType[] $catchTypes */
        $catchTypes = $initMethodCalls->getAllCallArgsFlat('catch', fn($arg) => $arg instanceof CatchType);
        $catchTypeLevels = collect($catchTypes)
            ->map(fn(CatchType $c) => new Inspector($c))
            ->map(fn(Inspector $c) => $c->getRawLevel() ?? $lastFallbackLevel)
            ->filter(fn(?string $level) => is_string($level))
            ->toArray();

        /** @var array<integer, string|null> $allLevels */
        $allLevels = array_merge($fallbackLevels, $catchTypeLevels);
        $allLevels = array_filter($allLevels, fn(?string $level) => !is_null($level)); // remove nulls

        foreach ($allLevels as $arg) {
            if (!in_array($arg, Settings::LOG_LEVELS)) {
                return true; // init error
            }
        }

        foreach ($initMethodCalls->getCalls() as $methodCall) {
            // allowed to be called with no parameters
            if (in_array($methodCall->getMethod(), $methodsAllowedToHaveNoParameters)) {
                continue;
            }
            // NOT allowed to be called without parameters
            if (!count($methodCall->getArgs())) {
                return true; // init error
            }
        }

        return false;
    }

    /**
     * Determine if an exception will be logged.
     *
     * @param CatchType|MethodCalls|null $willBeCaughtBy  The CatchType (or array of fallbackArgs) that catch the
     *                                                    exception.
     * @param MethodCalls                $initMethodCalls The method calls that build the fallback object.
     * @return boolean
     */
    private static function determineIfCallbacksWillBeRun(
        CatchType|MethodCalls|null $willBeCaughtBy,
        MethodCalls $initMethodCalls
    ): bool {

        if (!$willBeCaughtBy) {
            return false;
        }
        if (!$initMethodCalls->hasCall('callback')) {
            return false;
        }

        $report = true; // default value
        $rethrow = false; // default value
        foreach ($initMethodCalls->getCalls(['report', 'dontReport', 'rethrow', 'dontRethrow']) as $methodCall) {
            /** @var 'report'|'dontReport'|'rethrow'|'dontRethrow' $method */
            $method = $methodCall->getMethod();
            match ($method) {
                'report' => $report = (bool) ($methodCall->getArgs()[0] ?? true),
                'dontReport' => $report = false,
                'rethrow' => $rethrow = (bool) ($methodCall->getArgs()[0] ?? true),
                'dontRethrow' => $rethrow = false,
            };
        }

        if ((!$report) && (!$rethrow)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if an exception will be logged.
     *
     * @param CatchType|MethodCalls|null  $willBeCaughtBy      The CatchType (or array of fallbackArgs) that catch the
     *                                                         exception.
     * @param MethodCalls                 $fallbackCalls       The method calls that build the fallback object.
     * @param boolean|string|integer|null $callbackReturnValue The value that callbacks will return.
     * @return boolean
     */
    private static function determineIfExceptionWillBeLogged(
        CatchType|MethodCalls|null $willBeCaughtBy,
        MethodCalls $fallbackCalls,
        bool|string|int|null $callbackReturnValue
    ): bool {

        if ($callbackReturnValue === false) {
            return false;
        }

        if (!$willBeCaughtBy) {
            return false;
        }

        // what would the fall-back settings do
        $fallbackReport = null;
        foreach ($fallbackCalls->getCalls(['report', 'dontReport']) as $methodCall) {
            /** @var 'report'|'dontReport' $method */
            $method = $methodCall->getMethod();
            $fallbackReport = match ($method) {
                'report' => (bool) ($methodCall->getArgs()[0] ?? true),
                'dontReport' => false,
            };
        }

        $defaultReport = true; // default true

        // if it's a CatchType that catches the exception
        if ($willBeCaughtBy instanceof CatchType) {
            $inspector = new Inspector($willBeCaughtBy);
            return $inspector->getRawReport() ?? $fallbackReport ?? $defaultReport;
        }

        // or if it's the fallback that catches the exception
        return $fallbackReport ?? $defaultReport;
    }

    /**
     * Determine if a thrown exception should be detected by the calling code.
     *
     * @param string|null                 $exceptionToTrigger  The exception type to trigger (if any).
     * @param CatchType|MethodCalls|null  $willBeCaughtBy      The CatchType (or array of fallbackArgs) that catch the
     *                                                         exception.
     * @param MethodCalls                 $fallbackCalls       The method calls that build the fallback object.
     * @param boolean|string|integer|null $callbackReturnValue The value that callbacks will return.
     * @return boolean
     */
    private static function willExceptionBeThrownToCaller(
        ?string $exceptionToTrigger,
        CatchType|MethodCalls|null $willBeCaughtBy,
        MethodCalls $fallbackCalls,
        bool|string|int|null $callbackReturnValue
    ): bool {

        if (!$exceptionToTrigger) {
            return false;
        }

        if ($callbackReturnValue === false) {
            return false;
        }

        if (!$willBeCaughtBy) {
            return true;
        }



        // what would the fall-back settings do
        $fallbackRethrow = null;
        foreach ($fallbackCalls->getCalls(['rethrow', 'dontRethrow']) as $methodCall) {
            /** @var 'rethrow'|'dontRethrow' $method */
            $method = $methodCall->getMethod();
            $fallbackRethrow = match ($method) {
                'rethrow' => (bool) ($methodCall->getArgs()[0] ?? true),
                'dontRethrow' => false,
            };
        }

        $defaultRethrow = false; // default false

        // if it's a CatchType that catches the exception
        if ($willBeCaughtBy instanceof CatchType) {
            $inspector = new Inspector($willBeCaughtBy);
            return $inspector->getRawRethrow() ?? $fallbackRethrow ?? $defaultRethrow;
        }

        // or if it's the fallback that catches the exception
        return $fallbackRethrow ?? $defaultRethrow;
    }


    /**
     * Test that the Clarity object's methods set the log-levels properly.
     *
     * @test
     * @dataProvider logLevelDataProvider
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the Clarity object.
     * @param string      $expectedLevel   The log reporting level to expect.
     * @return void
     * @throws Exception When an initialisation method can't be called.
     */
    public static function test_the_log_levels(MethodCalls $initMethodCalls, string $expectedLevel): void
    {
        $callback = fn(Context $context) => self::assertSame($expectedLevel, $context->getLevel());

        // initialise the Clarity object
        $clarity = Clarity::prime(self::throwExceptionClosure())
            ->callback($callback); // to inspect the log-level

        foreach ($initMethodCalls->getCalls() as $methodCall) {

            $method = $methodCall->getMethod();
            $args = $methodCall->getArgs();

            $toCall = [$clarity, $method];
            if (is_callable($toCall)) {
                /** @var Clarity $clarity */
                $clarity = call_user_func_array($toCall, $args);
            } else {
                throw new Exception("Can't call method $method on class Clarity");
            }
        }

        if (Environment::isLaravel()) {
            // the only way to actually change the log reporting level is to update app/Exceptions/Handler.php
            // otherwise it's reported as "error"
            self::logShouldReceive(Settings::REPORTING_LEVEL_ERROR);
        } else {
            throw new Exception('Log checking needs to be updated for the current framework');
        }

        $clarity->execute();
    }

    /**
     * Provide data for the test_the_log_levels test.
     *
     * @return array<integer, array<string, MethodCalls|string>>
     */
    public static function logLevelDataProvider(): array
    {
        $return = [];

        // call ->level($logLevel)
        foreach (Settings::LOG_LEVELS as $logLevel) {
            $return[] = [
                'initMethodCalls' => MethodCalls::add('level', [$logLevel]),
                'expectedLevel' => $logLevel,
            ];
        }

        // call ->debug(), ->info(), …, ->emergency()
        foreach (Settings::LOG_LEVELS as $logLevel) {
            $return[] = [
                'initMethodCalls' => MethodCalls::add($logLevel),
                'expectedLevel' => $logLevel,
            ];
        }

        return $return;
    }



    /**
     * Test that the order the CatchTypes are defined in, matters.
     *
     * @test
     *
     * @return void
     * @throws Exception Exceptions that weren't supposed to be caught.
     */
    public static function test_that_the_catch_type_order_matters(): void
    {
        $callback1 = function () use (&$callback1Ran) {
            $callback1Ran = true;
        };
        $callback2 = function () use (&$callback2Ran) {
            $callback2Ran = true;
        };
        $callback3 = function () use (&$callback3Ran) {
            $callback3Ran = true;
        };

        $catchType1 = CatchType::catch(Exception::class)->callback($callback1);
        $catchType2 = CatchType::catch(Exception::class)->callback($callback2);
        $catchType3 = CatchType::catch(Exception::class)->callback($callback3);



        $callback1Ran = $callback2Ran = $callback3Ran = false;
        Clarity::prime(self::throwExceptionClosure())
            ->catch($catchType1)
            ->catch($catchType2)
            ->catch($catchType3)
            ->execute();

        self::assertTrue($callback1Ran);
        self::assertFalse($callback2Ran);
        self::assertFalse($callback3Ran);



        $callback1Ran = $callback2Ran = $callback3Ran = false;
        Clarity::prime(self::throwExceptionClosure())
            ->catch($catchType2)
            ->catch($catchType3)
            ->catch($catchType1)
            ->execute();

        self::assertFalse($callback1Ran);
        self::assertTrue($callback2Ran);
        self::assertFalse($callback3Ran);



        $callback1Ran = $callback2Ran = $callback3Ran = false;
        Clarity::prime(self::throwExceptionClosure())
            ->catch($catchType3)
            ->catch($catchType1)
            ->catch($catchType2)
            ->execute();

        self::assertFalse($callback1Ran);
        self::assertFalse($callback2Ran);
        self::assertTrue($callback3Ran);
    }



    /**
     * Test that will pass the correct parameters to callbacks.
     *
     * @test
     * @dataProvider callbackParameterDataProvider
     *
     * @param callable $callback                    The callback to run.
     * @param boolean  $expectExceptionToBeRethrown Whether to expect an exception or not.
     * @return void
     */
    public static function test_callback_parameters(callable $callback, bool $expectExceptionToBeRethrown): void
    {
        $caughtException = false;
        try {
            Clarity::prime(self::throwExceptionClosure())
                ->callback($callback)
                ->execute();
        } catch (Throwable) {
            $caughtException = true;
        }

        self::assertSame($expectExceptionToBeRethrown, $caughtException);
    }

    /**
     * DataProvider for test_callback_parameters.
     *
     * @return array<integer, array<integer, callable|bool>>
     */
    public static function callbackParameterDataProvider(): array
    {
        // callbacks that don't cause an exception
        $callbacks = [];
        $callbacks[] = function ($exception) {
            self::assertInstanceOf(Exception::class, $exception);
        };

        $callbacks[] = function (Throwable $exception) {
            self::assertInstanceOf(Exception::class, $exception);
        };

        $callbacks[] = function ($e) {
            self::assertInstanceOf(Exception::class, $e);
        };

        $callbacks[] = function (Throwable $e) {
            self::assertInstanceOf(Exception::class, $e);
        };

        $callbacks[] = function (Context $a) {
            self::assertTrue(true); // $a will be a Context because of the parameter definition
        };

        $callbacks[] = function (Request $a) {
            self::assertTrue(true); // $a will be a Request because of the parameter definition
        };

        $callbacks = collect($callbacks)
            ->map(fn(callable $callback) => [$callback, false])
            ->values()
            ->toArray();



        // callbacks that cause an exception
        $exceptionCallbacks = [];
        $exceptionCallbacks[] = function ($a) {
        };
        $exceptionCallbacks[] = function (Throwable $throwable) {
        };

        $exceptionCallbacks = collect($exceptionCallbacks)
            ->map(fn(callable $callback) => [$callback, true])
            ->values()
            ->toArray();



        /** @var array<integer, array<integer, callable|bool>> $return */
        $return = array_merge($callbacks, $exceptionCallbacks);

        return $return;
    }

    /**
     * Test what happens when the callback alters the report and rethrow settings.
     *
     * @test
     * @dataProvider callbackContextEditDataProvider
     *
     * @param boolean $report  The report value the callback should set.
     * @param boolean $rethrow The rethrow value the callback should set.
     * @param mixed   $return  The value for the callback to return.
     * @return void
     */
    public static function test_callback_that_updates_the_context_object(
        bool $report,
        bool $rethrow,
        mixed $return
    ): void {

        $callback1Ran = false;
        $callback1 = function (Context $context) use ($report, $rethrow, $return, &$callback1Ran) {
            $context->setReport($report);
            $context->setRethrow($rethrow);
            $callback1Ran = true;
            return $return;
        };

        $callback2Ran = false;
        $callback2 = function () use (&$callback2Ran) {
            $callback2Ran = true;
        };



        $report && ($return !== false)
            ? self::logShouldReceive(Settings::REPORTING_LEVEL_ERROR)
            : self::logShouldNotReceive(Settings::REPORTING_LEVEL_ERROR);



        // run the closure
        $caughtException = false;
        try {
            Clarity::prime(self::throwExceptionClosure())
            ->callback($callback1)
            ->callback($callback2)
            ->execute();
        } catch (Throwable) {
            $caughtException = true;
        }



        self::assertSame($rethrow && ($return !== false), $caughtException);
        self::assertTrue($callback1Ran);
        self::assertSame(($report || $rethrow) && ($return !== false), $callback2Ran);
    }

    /**
     * Provide data for the test_callback_that_updates_the_context_object test.
     *
     * @return array<integer, array<string, boolean|null>>
     */
    public static function callbackContextEditDataProvider(): array
    {
        return [
            ['report' => false, 'rethrow' => false, 'return' => null],
            ['report' => true,  'rethrow' => false, 'return' => null],
            ['report' => false, 'rethrow' => true,  'return' => null],
            ['report' => true,  'rethrow' => true,  'return' => null],
            ['report' => false, 'rethrow' => false, 'return' => false],
            ['report' => true,  'rethrow' => false, 'return' => false],
            ['report' => false, 'rethrow' => true,  'return' => false],
            ['report' => true,  'rethrow' => true,  'return' => false],
        ];
    }



    /**
     * Test that global callbacks are called.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_global_callbacks_are_called(): void
    {
        $order = [];

        $globalCallback1 = function () use (&$order) {
            $order[] = 'gc1';
        };
        $globalCallback2 = function () use (&$order) {
            $order[] = 'gc2';
        };
        $globalCallback3 = function () use (&$order) {
            $order[] = 'gc3';
        };
        $globalCallback4 = function () use (&$order) {
            $order[] = 'gc4';
        };

        $callback1 = function () use (&$order) {
            $order[] = 'c1';
        };
        $callback2 = function () use (&$order) {
            $order[] = 'c2';
        };
        $callback3 = function () use (&$order) {
            $order[] = 'c3';
        };
        $callback4 = function () use (&$order) {
            $order[] = 'c4';
        };



        Clarity::globalCallback($globalCallback1);
        Clarity::prime(self::throwExceptionClosure())
            ->callback($callback1)
            ->execute();

        Clarity::globalCallbacks($globalCallback2);
        Clarity::prime(self::throwExceptionClosure())
            ->callback($callback2)
            ->execute();

        Clarity::globalCallbacks([$globalCallback3], $globalCallback4);
        Clarity::prime(self::throwExceptionClosure())
            ->callback($callback3)
            ->callback($callback4)
            ->execute();

        self::assertSame(['gc1', 'c1', 'gc1', 'gc2', 'c2', 'gc1', 'gc2', 'gc3', 'gc4', 'c3', 'c4'], $order);
    }



    /**
     * Test that callbacks aren't called when they're not supposed to be.
     *
     * @test
     * @dataProvider callbacksArentRunDataProvider
     *
     * @param boolean $report               Whether to report or not.
     * @param boolean $rethrow              Whether to rethrow or not.
     * @param boolean $expectCallbacksToRun Whether the callbacks should be run or not.
     * @return void
     */
    public static function test_that_callbacks_arent_run(bool $report, bool $rethrow, bool $expectCallbacksToRun): void
    {
        $order = [];
        $callback1 = function () use (&$order) {
            $order[] = 1;
        };
        $callback2 = function () use (&$order) {
            $order[] = 2;
        };

        Clarity::globalCallback($callback1);

        try {
            Clarity::prime(self::throwExceptionClosure())
                ->callback($callback2)
                ->report($report)
                ->rethrow($rethrow)
                ->execute();
        } catch (Throwable) {
        }

        if ($expectCallbacksToRun) {
            self::assertSame([1, 2], $order);
        } else {
            self::assertSame([], $order);
        }
    }

    /**
     * Data provider for test_that_callbacks_arent_run.
     *
     * @return array<integer, array<string, boolean>>
     */
    public static function callbacksArentRunDataProvider(): array
    {
        $return = [];
        foreach ([true, false] as $report) {
            foreach ([true, false] as $rethrow) {
                $return[] = [
                    'report' => $report,
                    'rethrow' => $rethrow,
                    'expectCallbacksToRun' => $report || $rethrow,
                ];
            }
        }

        return $return;
    }



    /**
     * Test that closure is run using dependency injection.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_closure_is_called_using_dependency_injection(): void
    {
        $closure = fn(Request $request) => self::assertInstanceOf(Request::class, $request);
        Clarity::run($closure);
    }



    /**
     * Test that the default values are set and returned properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_default_values(): void
    {
        $throwException = self::throwExceptionClosure();

        $return = Clarity::run($throwException);
        self::assertNull($return);

        $return = Clarity::prime($throwException)
            ->default('clarity-default')
            ->execute();
        self::assertSame('clarity-default', $return);

        $return = Clarity::prime($throwException)
            ->catch(CatchType::default('catch-type-default'))
            ->execute();
        self::assertSame('catch-type-default', $return);

        $return = Clarity::prime($throwException)
            ->catch(CatchType::default('catch-type-default'))
            ->default('clarity-default')
            ->execute();
        self::assertSame('catch-type-default', $return);

        $return = Clarity::prime($throwException)
            ->catch(new CatchType())
            ->default('clarity-default')
            ->execute();
        self::assertSame('clarity-default', $return);

        $return = Clarity::prime(fn() => 'success', $return)
            ->default('clarity-default')
            ->execute();
        self::assertSame('success', $return);

        $return = Clarity::prime($throwException)
            ->default(fn() => 'callable-default') // check that a callable default value is executed
            ->execute();
        self::assertSame('callable-default', $return);
    }



    /**
     * Test that calling execute returns the same value each time.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_execute_runs_the_callable_each_time(): void
    {
        $runCount = 0;
        $closure = function () use (&$runCount) {
            $runCount++;
            return 'abc';
        };
        $clarity = Clarity::prime($closure);

        self::assertSame('abc', $clarity->execute());
        self::assertSame(1, $runCount);

        self::assertSame('abc', $clarity->execute());
        self::assertSame(2, $runCount);
    }



    /**
     * Test that nested Clarity objects are captured when the inner one rethrows the exception.
     *
     * @test
     *
     * @return void
     */
    public static function test_nested_clarity_objects(): void
    {
        $line = __LINE__;
        $inspectContext = function (Context $context) use ($line) {

            $callMetaObjects = $context->getMeta(CallMeta::class);

            // that it has 2 steps
            self::assertSame(2, count($callMetaObjects));

            // and that the first step…
            self::assertSame(__FILE__, $callMetaObjects[0]->getFile());
            self::assertSame($line + 23, $callMetaObjects[0]->getLine());

            // is different to the second step
            self::assertSame(__FILE__, $callMetaObjects[1]->getFile());
            self::assertSame($line + 19, $callMetaObjects[1]->getLine());
        };

        $closure1 = fn() => Clarity::prime(self::throwExceptionClosure())
            ->rethrow()
            ->execute();

        Clarity::prime($closure1)
            ->callback($inspectContext)
            ->execute();
    }



    /**
     * Test that the "known" values of nested executions are detected properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_known_values_of_nested_executions_work(): void
    {
        $inspectContext = function (Context $context) {

            // collect the "known" details from each frame's Meta objects
            $allKnown = [];
            /** @var Frame $frame */
            foreach ($context->getCallStack() as $frame) {
                /** @var CallMeta $meta */
                foreach ($frame->getMeta(CallMeta::class) as $meta) {
                    $allKnown = array_merge($allKnown, $meta->getKnown());
                }
            }

            // compare them to the "known" details added to the Context object directly
            self::assertSame($allKnown, $context->getKnown());

            self::assertSame(2, count($allKnown));
            self::assertSame('known 1', $allKnown[0]);
            self::assertSame('known 2', $allKnown[1]);

            // check the "known" details by obtaining the CallMeta objects directly
            /** @var CallMeta[] $meta */
            $meta = $context->getMeta(CallMeta::class);
            self::assertSame(['known 1'], $meta[1]->getKnown());
            self::assertSame(['known 2'], $meta[2]->getKnown());
        };

        $closure2 = fn() => Clarity::prime(self::throwExceptionClosure())
            ->known('known 2')
            ->rethrow()
            ->execute();

        $closure1 = fn() => Clarity::prime($closure2)
            ->known('known 1')
            ->callback($inspectContext)
            ->execute();

        Clarity::prime($closure1)
            ->known('known-root')
            ->summary('summary')
            ->rethrow()
            ->execute();
    }



    /**
     * Test retrieval of the Context object.
     *
     * @test
     *
     * @return void
     */
    public function test_get_content(): void
    {
        $callback = function ($e, Context $context) {
            self::assertInstanceOf(Context::class, Clarity::getContext($e));
            self::assertSame($context, Clarity::getContext($e));

            self::assertInstanceOf(Context::class, Clarity::getContext());
            self::assertSame($context, Clarity::getContext());

            $e2 = new Exception('test');
            self::assertNull(Clarity::getContext($e2));
        };

        Clarity::prime(self::throwExceptionClosure())
            ->callback($callback)
            ->execute();
    }



    /**
     * Test that the prime() and then execute() method calls work.
     *
     * @test
     *
     * @return void
     */
    public static function test_prime_then_execute_methods(): void
    {
        self::assertSame('a', Clarity::prime(fn() => 'a')->execute());
    }



    /**
     * Test that the run method works.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_run_method(): void
    {
        self::assertSame('a', Clarity::run(fn() => 'a'));
    }



    /**
     * Test that initialisation exceptions are generated properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_init_exceptions(): void
    {
        // an invalid level is passed
        $exceptionWasThrown = false;
        try {
            Clarity::prime(fn() => 'a')->level('BLAH');
        } catch (ClarityInitialisationException) {
            $exceptionWasThrown = true;
        }
        self::assertTrue($exceptionWasThrown);
    }



    /**
     * Test that Clarity doesn't interfere with Laravel's normal error reporting functionality.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_normal_report_functionality_isnt_interfered_with(): void
    {
        if (!Environment::isLaravel()) {
            self::markTestSkipped("This test only runs when using Laravel");
        }

        self::logShouldReceive(Settings::REPORTING_LEVEL_ERROR);
        report(new Exception('test'));
    }


    /**
     * Test that things run properly when Clarity is disabled.
     *
     * @test
     * @dataProvider disableClarityDataProvider
     *
     * @param class-string|null $exceptionToTrigger            The exception type to trigger (if any).
     * @param boolean           $useCallback                   Pass a callback to Clarity.
     * @param boolean           $report                        Report the exception.
     * @param boolean           $rethrow                       Rethrow the exception.
     * @param boolean           $expectCallbackToBeRun         Except the exception callback to be run?.
     * @param boolean           $expectExceptionToBeLogged     Expect the exception to be logged?.
     * @param boolean           $expectExceptionThrownToCaller Except the exception to be thrown to the caller?.
     * @return void
     */
    public static function test_that_things_run_when_clarity_is_disabled(
        ?string $exceptionToTrigger,
        bool $useCallback,
        bool $report,
        bool $rethrow,
        bool $expectCallbackToBeRun,
        bool $expectExceptionToBeLogged,
        bool $expectExceptionThrownToCaller,
    ): void {

        self::resolveFrameworkConfig()->updateConfig([Settings::LARAVEL_CONFIG_NAME . '.enabled' => false]);

        // set up the closure to run
        $intendedReturnValue = mt_rand();
        $closureRunCount = 0;
        $closure = function () use (&$closureRunCount, $intendedReturnValue, $exceptionToTrigger) {
            $closureRunCount++;
            if (!is_null($exceptionToTrigger)) {
                /** @var Throwable $exception */
                $exception = new $exceptionToTrigger(self::$exceptionMessage);
                throw $exception;
            }
            return $intendedReturnValue;
        };



        $exceptionCallbackWasRun = false;
        $callback = function (Context $context, Throwable $e) use ($report, $rethrow, &$exceptionCallbackWasRun) {

            try {

                $callStack = $context->getCallStack();
                $trace = $context->getTrace();

                // no meta-objects will be collected when Clarity is disabled
                self::assertSame($e, $context->getException());
                self::assertSame(0, count($context->getMeta())); // doesn't track meta-data
                self::assertSame([], $context->getKnown()); // doesn't track "known"
                self::assertSame(['some-channel'], $context->getChannels());
                self::assertSame(Settings::REPORTING_LEVEL_DEBUG, $context->getLevel());
                self::assertSame($report, $context->getReport());
                self::assertSame($rethrow, $context->getRethrow());

                self::assertTrue(count($callStack) > 0); // has frames
                self::assertNull($callStack->getLastApplicationFrameIndex());
                self::assertNull($callStack->getLastApplicationFrame());
                self::assertNull($callStack->getExceptionThrownFrameIndex());
                self::assertNull($callStack->getExceptionThrownFrame());
                self::assertNull($callStack->getExceptionCaughtFrameIndex());
                self::assertNull($callStack->getExceptionCaughtFrame());

                self::assertTrue(count($trace) > 0); // has frames
                self::assertNull($trace->getLastApplicationFrameIndex());
                self::assertNull($trace->getLastApplicationFrame());
                self::assertNull($trace->getExceptionThrownFrameIndex());
                self::assertNull($trace->getExceptionThrownFrame());
                self::assertNull($trace->getExceptionCaughtFrameIndex());
                self::assertNull($trace->getExceptionCaughtFrame());

            } catch (Throwable $e) {
                dd('Exception was thrown during callback');
            }

            $exceptionCallbackWasRun = true;
        };



        $default = mt_rand();
        $clarity = Clarity::prime($closure, $exception)
            ->summary('something')
            ->context(['something'])
            ->default($default)
            ->debug()
            ->channels(['some-channel'])
            ->known('known-1234')
            ->report($report)
            ->rethrow($rethrow);
        if ($useCallback) {
            $clarity->callback($callback);
        }



        // Note: the actual level used is handled by the app/Exceptions/Handler.php
        // in Laravel, it's logged as error unless updated
        $expectExceptionToBeLogged
            ? self::logShouldReceive(Settings::REPORTING_LEVEL_ERROR)
            : self::logShouldNotReceive(Settings::REPORTING_LEVEL_ERROR);



        // run the closure
        $exceptionWasDetectedOutside = false;
        $returnValue = null;
        try {
            $returnValue = $clarity->execute();
        } catch (Throwable $e) {
//            dump("Exception: \"{$e->getMessage()}\" in {$e->getFile()}:{$e->getLine()}");
            $exceptionWasDetectedOutside = true;
        }



        self::assertSame(1, $closureRunCount);
        self::assertSame($expectCallbackToBeRun, $exceptionCallbackWasRun);
        self::assertSame($expectExceptionThrownToCaller, $exceptionWasDetectedOutside);

        if (is_null($exceptionToTrigger)) {
            self::assertSame($intendedReturnValue, $returnValue);
        } else {
            $expectExceptionThrownToCaller
                ? self::assertNull($returnValue)
                : self::assertSame($default, $returnValue);
        }

        if ($exceptionToTrigger) {
            self::assertInstanceOf($exceptionToTrigger, $exception);
        } else {
            self::assertNull($exception);
        }
    }

    /**
     * Provide data for test_that_things_run_when_clarity_is_disabled.
     *
     * @return array<integer, array<string, boolean|string|null>>
     */
    public static function disableClarityDataProvider(): array
    {
        $triggerExceptionTypes = [
            null, // don't throw an exception
            Exception::class,
            InvalidArgumentException::class,
        ];

        $return = [];

        foreach ($triggerExceptionTypes as $exceptionToTrigger) {
            foreach ([true, false] as $useCallback) {
                foreach ([true, false] as $report) {
                    foreach ([true, false] as $rethrow) {

                        $expectCallbackToBeRun = $exceptionToTrigger && $useCallback && ($report || $rethrow);
                        $expectExceptionToBeLogged = $exceptionToTrigger && $report;
                        $expectExceptionThrownToCaller = $exceptionToTrigger && $rethrow;

                        $return[] = [
                            'exceptionToTrigger' => $exceptionToTrigger,
                            'useCallback' => $useCallback,
                            'report' => $report,
                            'rethrow' => $rethrow,
                            'expectCallbackToBeRun' => $expectCallbackToBeRun,
                            'expectExceptionToBeLogged' => $expectExceptionToBeLogged,
                            'expectExceptionThrownToCaller' => $expectExceptionThrownToCaller,
                        ];
                    }
                }
            }
        }

        return $return;
    }





    /**
     * Build a closure that throws a new exception.
     *
     * @return callable
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    private static function throwExceptionClosure(): callable
    {
        return fn() => throw new Exception(self::$exceptionMessage);
    }

    /**
     * Assert that the logger should be called once.
     *
     * @param string $level The log reporting level to check.
     * @return void
     * @throws Exception When the framework isn't recognised.
     */
    private static function logShouldReceive(string $level): void
    {
        if (!Environment::isLaravel()) {
            throw new Exception('Log checking needs to be updated for the current framework');
        }

        Log::shouldReceive($level)->once();
    }

    /**
     * Assert that the logger should not be called at all.
     *
     * @param string $level The log reporting level to check.
     * @return void
     * @throws Exception When the framework isn't recognised.
     */
    private static function logShouldNotReceive(string $level): void
    {
        if (!Environment::isLaravel()) {
            throw new Exception('Log checking needs to be updated for the current framework');
        }

        Log::shouldReceive($level)->atMost()->times(0);
    }
}
