<?php

namespace CodeDistortion\Clarity\Tests\Unit\Support\Context;

use CodeDistortion\Clarity\Clarity;
use CodeDistortion\Clarity\Exceptions\ClarityInitialisationException;
use CodeDistortion\Clarity\Settings;
use CodeDistortion\Clarity\Support\Context;
use CodeDistortion\Clarity\Support\Context\CallStack\CallStack;
use CodeDistortion\Clarity\Support\Context\CallStack\Frame;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\CallMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ContextMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ExceptionCaughtMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ExceptionThrownMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\LastApplicationFrameMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\SummaryMeta;
use CodeDistortion\Clarity\Support\MetaCallStack;
use CodeDistortion\Clarity\Tests\LaravelTestCase;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Throwable;

/**
 * Test the Context class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ContextTest extends LaravelTestCase
{
    /** @var string The message to use when throwing exceptions. */
    private static string $exceptionMessage = 'Something happened';


    /**
     * Test that Clarity can report the context details back when asked.
     *
     * @test
     *
     * @return void
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    public static function test_context(): void
    {
        $known = ['Ratione quis aliquid velit.'];
        $channels = ['stack'];
        $level = 'info';
        $report = true;
        $rethrow = false;

        $summary = 'Nobis soluta ducimus blanditiis et minima molestias.';
        $fakeId = 1525;
        $contextArray = ['id' => $fakeId];

        $callbackFinished = false;
        $clarity = Clarity::prime(fn() => throw new Exception(self::$exceptionMessage));
        // the next two are on separate lines, because this seems to alleviate the
        // "Call to method context() on an unknown class…" phpstan error
        $clarity->summary($summary);
        $clarity->context($contextArray);

        $clarity->known($known)
            ->channels($channels)
            ->level($level)
            ->report($report)
            ->rethrow($rethrow)
            ->callback(function (
                Throwable $e,
                Context $context
            ) use (
                $known,
                $channels,
                $level,
                $report,
                $rethrow,
                &$callbackFinished,
            ) {

                self::assertSame($e, $context->getException());
                self::assertInstanceOf(CallStack::class, $context->getCallStack());
                self::assertIsArray($context->getMeta());
                self::assertSame($known, $context->getKnown());
                self::assertSame($channels, $context->getChannels());
                self::assertSame($level, $context->getLevel());
                self::assertSame($report, $context->getReport());
                self::assertSame($rethrow, $context->getRethrow());

                $context2 = Clarity::getContext();
                self::assertNotNull($context2);
                self::assertSame($context, $context2);

                $context3 = Clarity::getContext($e);
                self::assertNotNull($context3);
                self::assertSame($context, $context3);

                $e2 = new Exception('test');
                self::assertNull(Clarity::getContext($e2));

                $callbackFinished = true;
            })
            ->execute();

        self::assertTrue($callbackFinished);
    }



    /**
     * Test that Clarity returns null when the Context object can't be resolved.
     *
     * @test
     *
     * @return void
     */
    public static function test_empty_context(): void
    {
        self::assertSame(null, Clarity::getContext());
    }



    /**
     * Test updating of values in the Context object.
     *
     * @test
     *
     * @return void
     */
    public static function test_context_crud(): void
    {
        // CREATE a new Context object
        $exception = new Exception('oh noes!!111');
        $channels = ['stack'];
        $level = Settings::REPORTING_LEVEL_DEBUG;
        $report = true;
        $rethrow = false;

        $basePath = (string) realpath(base_path('../../../..'));
        $projectRootDir = str_replace('/', DIRECTORY_SEPARATOR, $basePath);

        $context = new Context(
            $exception,
            new MetaCallStack(),
            -1,
            $projectRootDir,
            $channels,
            $level,
            $report,
            $rethrow
        );

        self::assertSame(1, Context::CONTEXT_VERSION);
        self::assertSame($exception, $context->getException());
        self::assertSame($channels, $context->getChannels());
        self::assertSame($level, $context->getLevel());
        self::assertSame($report, $context->getReport());
        self::assertSame($rethrow, $context->getRethrow());

        // test that the callstack SeekableIterator object has been rewound
        self::assertSame($context->getCallStack()[0], $context->getCallStack()->current());



        // UPDATE the Context object's values
        $channels = ['daily'];
        $level = Settings::REPORTING_LEVEL_INFO;
        $report = false;
        $rethrow = true;

        $context
            ->setChannels($channels)
            ->setLevel($level)
            ->setReport($report)
            ->setRethrow($rethrow);

        self::assertSame($channels, $context->getChannels());
        self::assertSame($level, $context->getLevel());
        self::assertSame($report, $context->getReport());
        self::assertSame($rethrow, $context->getRethrow());



        // try different combinations of values to UPDATE the Context object
        $context->setChannels('daily', ['something']);

        self::assertSame(['daily', 'something'], $context->getChannels());



        // set different log reporting levels
        $context->debug();
        self::assertSame(Settings::REPORTING_LEVEL_DEBUG, $context->getLevel());
        $context->info();
        self::assertSame(Settings::REPORTING_LEVEL_INFO, $context->getLevel());
        $context->notice();
        self::assertSame(Settings::REPORTING_LEVEL_NOTICE, $context->getLevel());
        $context->warning();
        self::assertSame(Settings::REPORTING_LEVEL_WARNING, $context->getLevel());
        $context->error();
        self::assertSame(Settings::REPORTING_LEVEL_ERROR, $context->getLevel());
        $context->critical();
        self::assertSame(Settings::REPORTING_LEVEL_CRITICAL, $context->getLevel());
        $context->alert();
        self::assertSame(Settings::REPORTING_LEVEL_ALERT, $context->getLevel());
        $context->emergency();
        self::assertSame(Settings::REPORTING_LEVEL_EMERGENCY, $context->getLevel());
    }



    /**
     * Test what happens when the project-root can't be resolved for some reason.
     *
     * @test
     *
     * @return void
     */
    public static function test_what_happens_when_the_project_root_cant_be_resolved(): void
    {
        $context = new Context(
            new Exception('oh noes!!111'),
            new MetaCallStack(),
            -1,
            '', // <<< no project root dir
            ['stack'],
            Settings::REPORTING_LEVEL_DEBUG,
            false,
            false
        );

        self::assertSame(1, count($context->getMeta(LastApplicationFrameMeta::class)));

        $frame = $context->getCallStack()->getLastApplicationFrame();
        self::assertInstanceOf(Frame::class, $frame);
        self::assertSame(__FILE__, $frame->getFile());
        self::assertSame(__LINE__ - 15, $frame->getLine());
    }



    /**
     * Test the retrieval of Meta objects.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_retrieval_of_meta_objects(): void
    {
        $callback = function (Context $context) {

            // retrieve all meta
            $meta = $context->getMeta();
            self::assertSame(8, count($meta));



            $meta = $context->getMeta(ExceptionThrownMeta::class);
            self::assertSame(1, count($meta));

            $meta = $context->getMeta(ExceptionCaughtMeta::class);
            self::assertSame(1, count($meta));

            $meta = $context->getMeta(LastApplicationFrameMeta::class);
            self::assertSame(1, count($meta));

            $meta = $context->getMeta(SummaryMeta::class);
            self::assertSame(2, count($meta));

            $meta = $context->getMeta(ContextMeta::class);
            self::assertSame(2, count($meta));

            $meta = $context->getMeta(CallMeta::class);
            self::assertSame(1, count($meta));



            $meta = $context->getMeta(SummaryMeta::class, ContextMeta::class);
            self::assertSame(4, count($meta));

            $meta = $context->getMeta([SummaryMeta::class, ContextMeta::class]);
            self::assertSame(4, count($meta));

            $meta = $context->getMeta(SummaryMeta::class, [ContextMeta::class]);
            self::assertSame(4, count($meta));

            $meta = $context->getMeta([SummaryMeta::class], ContextMeta::class);
            self::assertSame(4, count($meta));

            $meta = $context->getMeta([SummaryMeta::class], [ContextMeta::class]);
            self::assertSame(4, count($meta));

            $meta = $context->getMeta('abc');
            self::assertSame(0, count($meta));
        };

        Clarity::prime(self::throwExceptionClosure())
            ->summary('ONE')
            ->context(['111' => '111'])
            ->summary('TWO')
            ->context(['222' => '222'])
            ->callback($callback)
            ->execute();
    }



    /**
     * Test the generation and retrieval of the LastApplicationFrameMeta object from the callstack.
     *
     * @test
     *
     * @return void
     * @throws Exception When something…, not really, phpcs wants this.
     */
    public static function test_last_application_frame_meta(): void
    {
        $callback = function (Context $context, Exception $e) {

            $path = 'tests/Unit/Support/Context/ContextTest.php';
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

            // find the last application (i.e. non-vendor) frame
            $frame = null;
            $frameIndex = 0;
            $count = 0;
            $exceptionTrace = array_reverse(self::getExceptionCallStack($e));
            foreach ($exceptionTrace as $tempFrame) {

                $file = is_string($tempFrame['file'] ?? null) ? $tempFrame['file'] : '';

                if (mb_substr($file, - mb_strlen($path)) == $path) {
                    $frame = $tempFrame;
                    $frameIndex = (count($exceptionTrace) - 1) - $count;

                    break;
                }
                $count++;
            }
            self::assertNotNull($frame);



            $callStack = $context->getCallStack();
            /** @var Frame $currentFrame */
            $currentFrame = $callStack[$frameIndex];

            self::assertSame($currentFrame, $context->getCallStack()->getLastApplicationFrame());
            self::assertTrue(!is_null($context->getCallStack()->getLastApplicationFrameIndex()));

            self::assertSame($currentFrame, $context->getTrace()->getLastApplicationFrame());
            self::assertTrue(!is_null($context->getTrace()->getLastApplicationFrameIndex()));

            $metaObjects1 = $currentFrame->getMeta(LastApplicationFrameMeta::class);
            $metaObjects2 = $context->getMeta(LastApplicationFrameMeta::class);

            self::assertSame(1, count($metaObjects1));
            self::assertSame($metaObjects1, $metaObjects2);

            /** @var LastApplicationFrameMeta $meta1 */
            $meta1 = $metaObjects1[0];

            self::assertInstanceOf(LastApplicationFrameMeta::class, $meta1);

            self::assertSame($frame['file'], $meta1->getFile());
            self::assertSame($frame['line'], $meta1->getLine());

            self::assertSame(__FILE__, $meta1->getfile());

            $context->getException() instanceof BindingResolutionException
                ? self::assertSame(__LINE__ + 9, $meta1->getLine()) // last frame is a VENDOR file
                : self::assertSame(__LINE__ + 19, $meta1->getLine()); // last frame is an APPLICATION file
        };

        /** @var BindingResolutionException $exception */
        $exception = null;
        try {
            // generate an exception to use below
            /** @phpstan-ignore-next-line  */
            app()->make(DoesNotExist::class);
        } catch (Throwable $e) {
            $exception = $e;
        }

        // test when the last frame is a VENDOR frame
        Clarity::prime(fn() => throw $exception)
            ->callback($callback)
            ->execute();

        // test when the last frame is an APPLICATION frame
        Clarity::prime(fn() => throw new Exception(self::$exceptionMessage))
            ->callback($callback)
            ->execute();
    }


    /**
     * Test the generation and retrieval of the ExceptionThrownMeta object from the callstack.
     *
     * @test
     *
     * @return void
     * @throws Exception When something…, not really, phpcs wants this.
     */
    public static function test_exception_thrown_meta(): void
    {
        $callback = function (Context $context, Exception $e) {

            $exceptionTrace = array_reverse(self::getExceptionCallStack($e));
            $frame = $exceptionTrace[0];

            $callStack = $context->getCallStack();

            $frameIndex = count($callStack) - 1;

            /** @var Frame $currentFrame */
            $currentFrame = $callStack[$frameIndex];

            self::assertSame($currentFrame, $context->getCallStack()->getExceptionThrownFrame());
            self::assertTrue(!is_null($context->getCallStack()->getExceptionThrownFrameIndex()));

            self::assertSame($currentFrame, $context->getTrace()->getExceptionThrownFrame());
            self::assertTrue(!is_null($context->getTrace()->getExceptionThrownFrameIndex()));

            $metaObjects1 = $currentFrame->getMeta(ExceptionThrownMeta::class);
            $metaObjects2 = $context->getMeta(ExceptionThrownMeta::class);

            self::assertSame(1, count($metaObjects1));
            self::assertSame($metaObjects1, $metaObjects2);

            /** @var ExceptionThrownMeta $meta1 */
            $meta1 = $metaObjects1[0];

            self::assertInstanceOf(ExceptionThrownMeta::class, $meta1);

            self::assertSame($frame['file'], $meta1->getFile());
            self::assertSame($frame['line'], $meta1->getLine());

            $context->getException() instanceof BindingResolutionException
                ? null // the line inside the vendor directory may change
                : self::assertSame(__LINE__ + 19, $meta1->getLine()); // last frame is an APPLICATION file
        };

        /** @var BindingResolutionException $exception */
        $exception = null;
        try {
            // generate an exception to use below
            /** @phpstan-ignore-next-line  */
            app()->make(DoesNotExist::class);
        } catch (Throwable $e) {
            $exception = $e;
        }

        // test when the last frame is a VENDOR frame
        Clarity::prime(fn() => throw $exception)
            ->callback($callback)
            ->execute();

        // test when the last frame is an APPLICATION frame
        Clarity::prime(fn() => throw new Exception(self::$exceptionMessage))
            ->callback($callback)
            ->execute();
    }



    /**
     * Test the generation and retrieval of the ExceptionCaughtMeta object from the callstack.
     *
     * @test
     *
     * @return void
     * @throws Exception When something…, not really, phpcs wants this.
     */
    public static function test_exception_caught_meta(): void
    {
        $callback = function (Context $context, Exception $e) {

            /** @var Frame $currentFrame */
            $currentFrame = null;
            foreach ($context->getCallStack() as $tempFrame) {
                if ($tempFrame->exceptionWasCaughtHere()) {
                    $currentFrame = $tempFrame;
                    break;
                }
            }

            self::assertNotNull($currentFrame);

            self::assertSame($currentFrame, $context->getCallStack()->getExceptionCaughtFrame());
            self::assertTrue(!is_null($context->getCallStack()->getExceptionCaughtFrameIndex()));

            self::assertSame($currentFrame, $context->getTrace()->getExceptionCaughtFrame());
            self::assertTrue(!is_null($context->getTrace()->getExceptionCaughtFrameIndex()));

            /** @var Frame $currentFrame */
            $metaObjects1 = $currentFrame->getMeta(ExceptionCaughtMeta::class);
            $metaObjects2 = $context->getMeta(ExceptionCaughtMeta::class);

            self::assertSame(1, count($metaObjects1));
            self::assertSame($metaObjects1, $metaObjects2);

            /** @var ExceptionCaughtMeta $meta1 */
            $meta1 = $metaObjects1[0];

            self::assertInstanceOf(ExceptionCaughtMeta::class, $meta1);
        };

        /** @var BindingResolutionException $exception */
        $exception = null;
        try {
            // generate an exception to use below
            /** @phpstan-ignore-next-line  */
            app()->make(DoesNotExist::class);
        } catch (Throwable $e) {
            $exception = $e;
        }

        // test when the last frame is a VENDOR frame
        Clarity::prime(fn() => throw $exception)
            ->callback($callback)
            ->execute();

        // test when the last frame is an APPLICATION frame
        Clarity::prime(fn() => throw new Exception(self::$exceptionMessage))
            ->callback($callback)
            ->execute();
    }



    /**
     * Check that an exception is thrown when invalid meta-data is added to the MetaCallStack, and consumed by Context.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_an_invalid_meta_type_causes_an_exception(): void
    {
        $metaCallStack = new MetaCallStack();
        $metaCallStack->pushMetaData('invalid-meta-data-type', null); // invalid meta-data

        $exception = new Exception(self::$exceptionMessage);

        $basePath = (string) realpath(base_path('../../../..'));
        $projectRootDir = str_replace('/', DIRECTORY_SEPARATOR, $basePath);

        $exceptionOccurred = false;
        try {
            $context = new Context(
                $exception,
                $metaCallStack, // <<< contains the invalid meta-data from above
                0,
                $projectRootDir,
                [],
                Settings::REPORTING_LEVEL_DEBUG,
                true,
                false,
            );
            $context->getMeta(); // force $context to process the MetaCallStack

        } catch (ClarityInitialisationException $e) {
            $exceptionOccurred = true;
        }

        self::assertTrue($exceptionOccurred);
    }



    /**
     * Test that the file and line numbers are shifted by 1 frame when building the callstack.
     *
     * @test
     *
     * @return void
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    public static function test_that_the_callstack_file_and_line_numbers_are_shifted(): void
    {
        $callback = function (Context $context) {

            // find the frames from within this file
            $frames = [];
            /** @var Frame $frame */
            foreach ($context->getCallStack() as $frame) {
                if ($frame->getFile() == __FILE__) {
                    $frames[] = $frame;
                }
            }

            $frame = $frames[0];
            self::assertSame('test_that_the_callstack_file_and_line_numbers_are_shifted', $frame->getFunction());
            self::assertSame(__FILE__, $frame->getFile());
            self::assertSame(__LINE__ + 10, $frame->getLine());

            $frame = $frames[1];
            self::assertSame('CodeDistortion\Clarity\Tests\Unit\Support\Context\{closure}', $frame->getFunction());
            self::assertSame(__FILE__, $frame->getFile());
            self::assertSame(__LINE__ + 3, $frame->getLine());
        };

        Clarity::prime(fn() => throw new Exception(self::$exceptionMessage))
            ->callback($callback)
            ->execute();
    }



    /**
     * Test the retrieval of the callstack and stacktrace from the Context object.
     *
     * @test
     *
     * @return void
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    public static function test_retrieval_of_callstack_and_trace(): void
    {
        $callback = function (Context $context) {

            // the callstack and stacktrace objects are cloned each time (the frames won't be)
            self::assertNotSame($context->getCallStack(), $context->getCallStack());
            self::assertNotSame($context->getTrace(), $context->getTrace());

            // the LAST frame from a callstack will be from this file
            $callStack = $context->getCallStack();
            $lastIndex = count($callStack) - 1;
            /** @var Frame $frame */
            $frame = $callStack[$lastIndex];
            self::assertSame(__FILE__, $frame->getFile());

            // the FIRST frame from a stacktrace will be from this file
            $trace = $context->getTrace();
            /** @var Frame $frame */
            $frame = $trace[0];
            self::assertSame(__FILE__, $frame->getFile());
        };

        Clarity::prime(fn() => throw new Exception(self::$exceptionMessage))
            ->callback($callback)
            ->execute();
    }



    /**
     * Build the exception's callstack. Include the exception's location as a frame.
     *
     * @param Throwable $e The exception to use.
     * @return array<integer, mixed[]>
     */
    private static function getExceptionCallStack(Throwable $e): array
    {
        $exceptionCallStack = array_reverse($e->getTrace());

        // add the exception's location as the last frame
        $exceptionCallStack[] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        return $exceptionCallStack;
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
}
