<?php

namespace CodeDistortion\Clarity\Tests\Unit\Support\Context\CallStack;

use CodeDistortion\Clarity\Support\Common;
use CodeDistortion\Clarity\Support\Context\CallStack\Frame;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\Meta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\SummaryMeta;
use CodeDistortion\Clarity\Tests\PHPUnitTestCase;
use stdClass;

/**
 * Test the CallStackFrame class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class FrameTest extends PHPUnitTestCase
{
    /**
     * Test the CallStackFrame class.
     *
     * @test
     *
     * @return void
     */
    public static function test_call_stack_frame(): void
    {
        $projectRootDir = (string) realpath(__DIR__ . '/../../../../');

        self::testCallStackFrame(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            [],
            false,
            false,
            false,
            false,
            '',
        );

        self::testCallStackFrame(
            __FILE__,
            null,
            null,
            null,
            null,
            null,
            null,
            [],
            false,
            false,
            false,
            false,
            '',
        );

        self::testCallStackFrame(
            '/somewhere-else',
            null,
            null,
            null,
            null,
            null,
            null,
            [],
            false,
            false,
            false,
            false,
            $projectRootDir,
        );

        foreach ([true, false] as $thrownHere) {
            foreach ([true, false] as $caughtHere) {
                foreach ([true, false] as $isApplicationFrame) {
                    foreach ([true, false] as $isLastApplicationFrame) {

                        self::testCallStackFrame(
                            __FILE__,
                            123,
                            'someFunc',
                            'someClass',
                            new stdClass(),
                            'someType',
                            [1, 2],
                            [self::buildSummaryMeta()],
                            $thrownHere,
                            $caughtHere,
                            $isApplicationFrame,
                            $isLastApplicationFrame,
                            $projectRootDir,
                        );
                    }
                }
            }
        }
    }

    /**
     * Build and test a CallStackFrame object.
     *
     * @param string|null  $file                   The file to use.
     * @param integer|null $line                   The line number to use.
     * @param string|null  $function               The function to use.
     * @param string|null  $class                  The class to use.
     * @param object|null  $object                 The object to use.
     * @param string|null  $type                   The type to use.
     * @param mixed[]|null $args                   The args to use.
     * @param Meta[]       $meta                   The meta objects to use.
     * @param boolean      $thrownHere             Whether the exception was thrown by this frame or not.
     * @param boolean      $caughtHere             Whether the exception was caught by this frame or not.
     * @param boolean      $isApplicationFrame     Whether this is an application frame or not.
     * @param boolean      $isLastApplicationFrame Whether this is the last application frame or not.
     * @param string       $projectRootDir         The project-root-dir to use.
     * @return void
     */
    private static function testCallStackFrame(
        ?string $file,
        ?int $line,
        ?string $function,
        ?string $class,
        ?object $object,
        ?string $type,
        ?array $args,
        array $meta,
        bool $thrownHere,
        bool $caughtHere,
        bool $isApplicationFrame,
        bool $isLastApplicationFrame,
        string $projectRootDir
    ): void {

        $projectFile = Common::resolveProjectFile((string) $file, $projectRootDir);

        $frame = new Frame(
            [
                'file' => $file,
                'line' => $line,
                'function' => $function,
                'class' => $class,
                'object' => $object,
                'type' => $type,
                'args' => $args,
            ],
            $projectFile,
            $meta,
            $thrownHere,
            $caughtHere,
            $isApplicationFrame,
            $isLastApplicationFrame,
        );

        self::assertSame((string) $file, $frame->getFile());
        self::assertSame($projectFile, $frame->getProjectFile());
        self::assertSame((int) $line, $frame->getLine());
        self::assertSame((string) $function, $frame->getFunction());
        self::assertSame((string) $class, $frame->getClass());
        self::assertSame($object, $frame->getObject());
        self::assertSame((string) $type, $frame->getType());
        self::assertSame($args, $frame->getArgs());
        self::assertSame($meta, $frame->getMeta());
        self::assertSame($meta, $frame->getMeta(SummaryMeta::class));
        self::assertSame($meta, $frame->getMeta([SummaryMeta::class]));
        self::assertSame($isApplicationFrame, $frame->isApplicationFrame());
        self::assertSame($isLastApplicationFrame, $frame->isLastApplicationFrame());
        self::assertSame(!$isApplicationFrame, $frame->isVendorFrame());
        self::assertSame($thrownHere, $frame->exceptionWasThrownHere());
        self::assertSame($caughtHere, $frame->exceptionWasCaughtHere());
    }

    /**
     * Build a dummy SummaryMeta object.
     *
     * @return SummaryMeta
     */
    private static function buildSummaryMeta(): SummaryMeta
    {
        $frameData = [
            'file' => 'somewhere',
            'line' => 123,
        ];

        return new SummaryMeta($frameData, 'somewhere', 'something');
    }
}
