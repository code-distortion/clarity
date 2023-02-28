<?php

namespace CodeDistortion\Clarity\Tests\Unit\Support;

use CodeDistortion\Clarity\Clarity;
use CodeDistortion\Clarity\Support\Context;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\CallMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ContextMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ExceptionCaughtMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ExceptionThrownMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\LastApplicationFrameMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\SummaryMeta;
use CodeDistortion\Clarity\Tests\LaravelTestCase;
use CodeDistortion\Clarity\Tests\Support\SomeOtherClass;
use Exception;

/**
 * Test the MetaCallStack class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class MetaCallStackTest extends LaravelTestCase
{
    /** @var string The message to use when throwing exceptions. */
    private static string $exceptionMessage = 'Something happened';



    /**
     * Test that meta-data gets recorded properly when repeated calls are made (i.e. the same lines are run again).
     *
     * @test
     *
     * @return void
     */
    public static function test_repeated_meta_data_calls(): void
    {
        $callback = function (Context $context) {
            self::assertSame(3, count($context->getMeta(SummaryMeta::class)));
            self::assertSame(2, count($context->getMeta(ContextMeta::class)));
            self::assertSame(2, count($context->getMeta(CallMeta::class)));
        };

        $clarity = Clarity::prime(fn() => SomeOtherClass::triggerAndRethrow())
            ->callback($callback);

        for ($count = 0; $count < 3; $count++) {
            $clarity
                ->summary("summary-$count")
                ->context([1 => "context-$count"])
                ->summary("summary-$count");
        }

        $clarity->execute();
    }



    /**
     * Test purge of meta-data after a fork in the execution tree.
     *
     * ---- closure2 (Clarity used, but no exception)
     *  \-- closure3 (Clarity not used, and throws exception + rethrow)
     *
     * Things registered in closure2 need to be purged.
     *
     * @test
     *
     * @return void
     */
    public static function test_purge_of_meta_data_after_fork_in_execution_tree(): void
    {
        $callback = function (Context $context) {

            self::assertSame(4, count($context->getMeta()));
            self::assertSame(1, count($context->getMeta(ExceptionThrownMeta::class)));
            self::assertSame(1, count($context->getMeta(ExceptionCaughtMeta::class)));
            self::assertSame(1, count($context->getMeta(LastApplicationFrameMeta::class)));
            self::assertSame(1, count($context->getMeta(CallMeta::class)));

            // the meta-data registered in closure2 shouldn't be here
            $meta = $context->getMeta(CallMeta::class);
            self::assertSame(__LINE__ + 18, $meta[0]->getLine());
        };

        $closure3 = function () {
            throw new Exception(self::$exceptionMessage);
        };

        $closure2 = function () {
            Clarity::prime(fn() => 'a')->summary('summary2')->rethrow()->execute();
        };

        $closure1 = function () use ($closure2, $closure3) {
            $closure2();
            $closure3();
        };

        Clarity::prime($closure1)
            ->callback($callback)
            ->execute();
    }



    /**
     * Test that meta-data is purged when making calls, returning, and branching.
     *
     * Each closure will alternately throw an exception, catch, re-throw.
     *
     * e.g. ----- oneA() -- oneB()
     *        \-- twoA() -- twoB()
     *
     * @test
     * @dataProvider metaPurgingDataProvider
     *
     * @param boolean                 $oneATrigger Should closure "one-a" trigger an exception?.
     * @param boolean                 $oneARethrow Should closure "one-a" re-throw exceptions?.
     * @param boolean                 $oneBTrigger Should closure "one-b" trigger an exception?.
     * @param boolean                 $oneBRethrow Should closure "one-b" re-throw exceptions?.
     * @param boolean                 $twoATrigger Should closure "two-a" trigger an exception?.
     * @param boolean                 $twoARethrow Should closure "two-a" re-throw exceptions?.
     * @param boolean                 $twoBTrigger Should closure "two-b" trigger an exception?.
     * @param boolean                 $twoBRethrow Should closure "two-b" re-throw exceptions?.
     * @param array<integer, mixed[]> $expected    The expected meta-data.
     * @return void
     */
    public static function test_meta_purging(
        bool $oneATrigger,
        bool $oneARethrow,
        bool $oneBTrigger,
        bool $oneBRethrow,
        bool $twoATrigger,
        bool $twoARethrow,
        bool $twoBTrigger,
        bool $twoBRethrow,
        array $expected
    ): void {

        $allCondensedMetaData = [];
        $callback = function (Context $context) use (&$allCondensedMetaData) {

            $condensedMetaData = [];
            foreach ($context->getMeta() as $meta) {

                if ($meta instanceof LastApplicationFrameMeta) {
                    $condensedMetaData[] = 'last-application-frame';
                } elseif ($meta instanceof ExceptionThrownMeta) {
                    $condensedMetaData[] = 'exception-thrown';
                } elseif ($meta instanceof ExceptionCaughtMeta) {
                    $condensedMetaData[] = 'exception-caught';
                } elseif ($meta instanceof SummaryMeta) {
                    $condensedMetaData[] = $meta->getSummary();
                } elseif ($meta instanceof ContextMeta) {
                    $condensedMetaData[] = $meta->getContext()[0];
                } elseif ($meta instanceof CallMeta) {
                    $condensedMetaData[] = [
                        'known' => $meta->getKnown()[0] ?? null,
                        'caughtHere' => $meta->wasCaughtHere(),
                    ];
                } else {
                    throw new Exception('Unexpected Meta class: ' . get_class($meta));
                }
            }

            $allCondensedMetaData[] = $condensedMetaData;
        };



        $oneB = function () use ($oneBTrigger, $oneBRethrow, $callback) {

            Clarity::summary('summary one-b')->context(['context one-b']);

            Clarity::prime(self::maybeThrow($oneBTrigger))
                ->known('known one-b')
                ->callback($callback)
                ->rethrow($oneBRethrow)
                ->execute();
        };

        $oneA = function () use ($oneATrigger, $oneARethrow, $oneB, $callback) {

            Clarity::summary('summary one-a')->context(['context one-a']);

            Clarity::prime(self::maybeThrow($oneATrigger, $oneB))
                ->known('known one-a')
                ->callback($callback)
                ->rethrow($oneARethrow)
                ->execute();
        };



        $twoB = function () use ($twoBTrigger, $twoBRethrow, $callback) {

            Clarity::summary('summary two-b')->context(['context two-b']);

            Clarity::prime(self::maybeThrow($twoBTrigger))
                ->known('known two-b')
                ->callback($callback)
                ->rethrow($twoBRethrow)
                ->execute();
        };

        $twoA = function () use ($twoATrigger, $twoARethrow, $twoB, $callback) {

            Clarity::summary('summary two-a')->context(['context two-a']);

            Clarity::prime(self::maybeThrow($twoATrigger, $twoB))
                ->known('known two-a')
                ->callback($callback)
                ->rethrow($twoARethrow)
                ->execute();
        };



        Clarity::summary('summary one')->context(['context one']);

        Clarity::prime($oneA)
            ->known('known one')
            ->callback($callback)
            ->execute();

        Clarity::summary('summary two')->context(['context two']);

        Clarity::prime($twoA)
            ->known('known two')
            ->callback($callback)
            ->execute();



        self::assertSame($expected, $allCondensedMetaData);
    }

    /**
     * DataProvider for test_meta_purging
     *
     * @return array<integer, mixed>
     */
    public static function metaPurgingDataProvider()
    {
        $return = [];

        foreach ([false, true] as $oneATrigger) {
            foreach ([false, true] as $oneARethrow) {
                foreach ([false, true] as $oneBTrigger) {
                    foreach ([false, true] as $oneBRethrow) {
                        foreach ([false, true] as $twoATrigger) {
                            foreach ([false, true] as $twoARethrow) {
                                foreach ([false, true] as $twoBTrigger) {
                                    foreach ([false, true] as $twoBRethrow) {

                                        $expected = self::resolveExpected(
                                            $oneATrigger,
                                            $oneARethrow,
                                            $oneBTrigger,
                                            $oneBRethrow,
                                            $twoATrigger,
                                            $twoARethrow,
                                            $twoBTrigger,
                                            $twoBRethrow,
                                        );

                                        if (!is_null($expected)) {
                                            $return[] = [
                                                'oneATrigger' => $oneATrigger,
                                                'oneARethrow' => $oneARethrow,
                                                'oneBTrigger' => $oneBTrigger,
                                                'oneBRethrow' => $oneBRethrow,
                                                'twoATrigger' => $twoATrigger,
                                                'twoARethrow' => $twoARethrow,
                                                'twoBTrigger' => $twoBTrigger,
                                                'twoBRethrow' => $twoBRethrow,
                                                'expected' => $expected,
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Determine what the outcome should be for the given trigger and rethrow settings.
     *
     * @param boolean $oneATrigger Should closure "one-a" trigger an exception?.
     * @param boolean $oneARethrow Should closure "one-a" re-throw exceptions?.
     * @param boolean $oneBTrigger Should closure "one-b" trigger an exception?.
     * @param boolean $oneBRethrow Should closure "one-b" re-throw exceptions?.
     * @param boolean $twoATrigger Should closure "two-a" trigger an exception?.
     * @param boolean $twoARethrow Should closure "two-a" re-throw exceptions?.
     * @param boolean $twoBTrigger Should closure "two-b" trigger an exception?.
     * @param boolean $twoBRethrow Should closure "two-b" re-throw exceptions?.
     * @return array<integer, mixed>|null
     */
    private static function resolveExpected(
        bool $oneATrigger,
        bool $oneARethrow,
        bool $oneBTrigger,
        bool $oneBRethrow,
        bool $twoATrigger,
        bool $twoARethrow,
        bool $twoBTrigger,
        bool $twoBRethrow,
    ): ?array {

        // "one-b" can't trigger, as "one-a" will trigger first
        if (($oneBTrigger) && ($oneATrigger)) {
            return null;
        }
        // "one-b" can't rethrow, as no exception will have been triggered
        if (($oneBRethrow) && (!$oneBTrigger)) {
            return null;
        }
        // "one-a" can't rethrow, as no exception will have been triggered
        if ($oneARethrow) {
            if ((!$oneATrigger) && (!$oneBTrigger || !$oneBRethrow)) {
                return null;
            }
        }

        // path "two" shouldn't be used, because an exception was already triggered via path "one"
        if (($twoATrigger || $twoBTrigger) && ($oneATrigger || $oneBTrigger)) {
            return null;
        }

        // "two-b" can't trigger, as "two-a" will trigger first
        if (($twoBTrigger) && ($twoATrigger)) {
            return null;
        }
        // "two-b" can't rethrow, as no exception will have been triggered
        if (($twoBRethrow) && (!$twoBTrigger)) {
            return null;
        }
        // "two-a" can't rethrow, as no exception will have been triggered
        if ($twoARethrow) {
            if ((!$twoATrigger) && (!$twoBTrigger || !$twoBRethrow)) {
                return null;
            }
        }


        // go down path "one"
        $snapshot = self::makeSnapshot(
            'one',
            $oneATrigger,
            $oneARethrow,
            $oneBTrigger,
            $oneBRethrow
        );

        // go down path "two"
        if (count($snapshot) <= 1) {
            $frameToAdd = self::makeFrame('one', false, false, false);
            $newSnapshot = self::makeSnapshot(
                'two',
                $twoATrigger,
                $twoARethrow,
                $twoBTrigger,
                $twoBRethrow,
                $frameToAdd
            );
            $snapshot = array_merge($snapshot, $newSnapshot);
        }

        return self::formatSnapshots(
            self::runSnapshotThroughSteps($snapshot)
        );
    }

    /**
     * Build one snapshot worth of frames.
     *
     * @param string       $name       The name that representing the call-level.
     * @param boolean      $aTrigger   Should closure "a" trigger an exception?.
     * @param boolean      $aRethrow   Should closure "a" re-throw exceptions?.
     * @param boolean      $bTrigger   Should closure "b" trigger an exception?.
     * @param boolean      $bRethrow   Should closure "b" re-throw exceptions?.
     * @param mixed[]|null $frameToAdd A frame to arbitrarily add to the beginning, provided some other frame was used.
     * @return array<integer, array<integer, mixed>>
     */
    private static function makeSnapshot(
        string $name,
        bool $aTrigger,
        bool $aRethrow,
        bool $bTrigger,
        bool $bRethrow,
        ?array $frameToAdd = null,
    ): array {

        $frameShouldBeIncluded = false;
        $exceptionIsActive = true;
        $snapshot = [];

        if ($bTrigger) {

            $snapshot[] = self::makeFrame(
                "$name-b",
                $exceptionIsActive,
                true,
                $bTrigger,
            );
            $frameShouldBeIncluded = true;

            if (!$bRethrow) {
                $exceptionIsActive = false;
            }
        }

        if ($aTrigger || $frameShouldBeIncluded) {

            $snapshot[] = self::makeFrame(
                "$name-a",
                $exceptionIsActive,
                true,
                $aTrigger,
            );
            $frameShouldBeIncluded = true;

            if (!$aRethrow) {
                $exceptionIsActive = false;
            }
        }

        if ($frameShouldBeIncluded) {
            $snapshot[] = self::makeFrame(
                $name,
                $exceptionIsActive,
                true,
                false,
            );
        }

        if (count($snapshot) && $frameToAdd) {
            $snapshot[] = $frameToAdd;
        }

        return $snapshot;
    }

    /**
     * Make one frame's worth of meta-object details.
     *
     * @param string  $name             The name that representing the call-level.
     * @param boolean $canCatch         Whether this frame will catch the exception at some point.
     * @param boolean $includeExecution Whether to include the execution meta-data or not.
     * @param boolean $willTrigger      Whether the exception will be triggered by this closure or not.
     * @return array<integer, mixed>
     */
    private static function makeFrame(
        string $name,
        bool $canCatch,
        bool $includeExecution,
        bool $willTrigger
    ): array {

        $return = [];

        $return[] = "summary $name";
        $return[] = "context $name";

        if ($includeExecution) {
            $return[] = [
                "known" => "known $name",
                "caughtHere" => null,
                "canCatch" => $canCatch, // will be removed later
            ];
        }

        if ($willTrigger) {
            $return[] = 'last-application-frame';
            $return[] = 'exception-thrown';
        }

        return $return;
    }

    /**
     * Run a call-stack snapshot through the steps, where each "frame" catches the exception.
     *
     * @param array<integer, array<integer, mixed>> $snapshot The snapshot to loop through.
     * @return array<integer, array<integer, array<integer, mixed>>>
     */
    private static function runSnapshotThroughSteps(array $snapshot): array
    {
        $snapshots = [];

        // make this many snapshots
        for ($count = 0; $count < count($snapshot); $count++) {

            // loop through each frame
            $newSnapshot = $snapshot;
            $foundFrameThatCaught = false;
            for ($index = 0; $index < count($newSnapshot); $index++) {

                if (!array_key_exists(2, $newSnapshot[$index])) {
                    continue;
                }

                if (!is_array($newSnapshot[$index][2])) {
                    continue;
                }

                /** @var array<string, mixed> $callMeta */
                $callMeta = $newSnapshot[$index][2];

                // remove the "known" setting if deeper frame has already caught it
                if ($foundFrameThatCaught) {
                    $callMeta['known'] = null;
                }

                // update the "caughtHere" setting for this frame
                $caughtHere = ($index == $count) && $callMeta['canCatch'];

                $foundFrameThatCaught = $foundFrameThatCaught || $caughtHere;

                $callMeta['caughtHere'] = $caughtHere;
                unset($callMeta['canCatch']);

                $newSnapshot[$index][2] = $callMeta;

                // add in the "exception-caught" meta-data after the "clarity" one
                if ($caughtHere) {
                    array_splice($newSnapshot[$index], 3, 0, ['exception-caught']);
                }
            }

            // keep the new snapshot if the exception was caught *somewhere*
            if ($foundFrameThatCaught) {
                $snapshots[] = $newSnapshot;
            }
        }

        return $snapshots;
    }

    /**
     * Format the snapshot to be in the format that's used when running the test.
     *
     * @param array<integer, array<integer, array<integer, mixed>>> $snapshots The snapshots to process.
     * @return array<integer, array<integer, mixed>>
     */
    private static function formatSnapshots(array $snapshots): array
    {
        foreach ($snapshots as $index => $snapshot) {

            // put the frames in the order that they'll appear
            $snapshot = array_reverse($snapshot);

            // flatten them, so the meta-details for each frame are in a single list
            $flattenedSnapshot = [];
            /** @var array<integer, mixed> $frame */
            foreach ($snapshot as $frame) {
                $flattenedSnapshot = array_merge($flattenedSnapshot, $frame);
            }

            $snapshots[$index] = $flattenedSnapshot;
        }

        return $snapshots;
    }


    /**
     * Test that meta-data gets stored when calling Clarity methods using call_user_func_array(..).
     *
     * @return void
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    public static function test_that_meta_data_is_remembered_when_calling_call_user_func_array()
    {
        $callbackCalled = false;
        $callback = function (Context $context) use (&$callbackCalled) {

            self::assertSame(1, count($context->getMeta(SummaryMeta::class)));

            $meta = $context->getMeta(SummaryMeta::class)[0];

            $method = 'test_that_meta_data_is_remembered_when_calling_call_user_func_array';
            $class = 'CodeDistortion\Clarity\Tests\Unit\Support\MetaCallStackTest';

            self::assertSame(__FILE__, $meta->getFile());
            self::assertSame(__LINE__ + 8, $meta->getLine());
            self::assertSame($method, $meta->getFunction());
            self::assertSame($class, $meta->getClass());

            $callbackCalled = true;
        };

        $clarity = Clarity::prime(fn() => throw new Exception('oh noes'));
        call_user_func_array([$clarity, 'summary'], ['something']);
        $clarity->callback($callback);
        $clarity->execute();

        self::assertTrue($callbackCalled);
    }



    /**
     * Build a closure that throws a new exception.
     *
     * @param boolean       $shouldThrow Whether an exception should be thrown or not.
     * @param callable|null $alternative The callable to use when not throwing an exception.
     * @return callable
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    private static function maybeThrow(bool $shouldThrow, ?callable $alternative = null): callable
    {
        $alternative ??= fn() => 'a';

        return $shouldThrow
            ? fn() => throw new Exception(self::$exceptionMessage)
            : $alternative;
    }
}
