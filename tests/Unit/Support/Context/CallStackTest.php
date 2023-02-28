<?php

namespace CodeDistortion\Clarity\Tests\Unit\Support\Context;

use CodeDistortion\Clarity\Support\Common;
use CodeDistortion\Clarity\Support\Context\CallStack\CallStack;
use CodeDistortion\Clarity\Support\Context\CallStack\Frame;
use CodeDistortion\Clarity\Tests\PHPUnitTestCase;
use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Test the CallStack class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class CallStackTest extends PHPUnitTestCase
{
    /**
     * Test the CallStack class
     *
     * @test
     *
     * @return void
     */
    public static function test_call_stack(): void
    {
        $implements = class_implements(CallStack::class);
        self::assertArrayHasKey('ArrayAccess', $implements);
        self::assertArrayHasKey('Countable', $implements);
        self::assertArrayHasKey('SeekableIterator', $implements);

        // build a CallStack object
        $frame1 = self::buildCallStackFrame();
        $frame2 = self::buildCallStackFrame();
        $frame3 = self::buildCallStackFrame();
        $frame4 = self::buildCallStackFrame();

        $callStack = new CallStack([$frame1, $frame2, $frame3]);



        // Countable
        self::assertSame(3, $callStack->count());
        self::assertSame(3, count($callStack));



        // ArrayAccess
        self::assertSame($frame1, $callStack[0]);
        self::assertSame($frame2, $callStack[1]);
        self::assertSame($frame3, $callStack[2]);
        self::assertTrue(isset($callStack[0]));
        self::assertTrue(isset($callStack[1]));
        self::assertTrue(isset($callStack[2]));
        self::assertSame(3, count($callStack));

        $callStack[3] = $frame4;
        self::assertSame($frame4, $callStack[3]);
        self::assertTrue(isset($callStack[3]));
        self::assertSame(4, count($callStack));

        unset($callStack[3]);
        self::assertFalse(isset($callStack[4]));
        self::assertSame(3, count($callStack));

        $exceptionOccurred = false;
        try {
            $callStack[0] = "Something that's not a Frame object";
        } catch (InvalidArgumentException) {
            $exceptionOccurred = true;
        }
        self::assertTrue($exceptionOccurred);



        // SeekableIterator

        // loop through with foreach loop
        $count = 0;
        foreach ($callStack as $frame) {
            if ($count == 0) {
                self::assertSame($frame1, $frame);
            } elseif ($count == 1) {
                self::assertSame($frame2, $frame);
            } elseif ($count == 2) {
                self::assertSame($frame3, $frame);
            }
            $count++;
        }

        // loop through manually
        $callStack->rewind();
        self::assertSame(0, $callStack->key());
        self::assertSame($frame1, $callStack->current());
        $callStack->next();
        self::assertSame(1, $callStack->key());
        self::assertSame($frame2, $callStack->current());
        $callStack->next();
        self::assertSame(2, $callStack->key());
        self::assertSame($frame3, $callStack->current());
        $callStack->next();
        self::assertSame(3, $callStack->key());
        self::assertSame(null, $callStack->current());

        // seek
        $callStack->seek(1);
        self::assertSame($frame2, $callStack->current());

        $threwException = false;
        try {
            $callStack->seek(-1);
        } catch (OutOfBoundsException) {
            $threwException = true;
        }
        self::assertTrue($threwException);

        // reverse
        $callStack->seek(1);
        $callStack->reverse(); // will reset back to 0, after reversing
        $count = 0;
        foreach ($callStack as $frame) {
            if ($count == 0) {
                self::assertSame($frame3, $frame);
            } elseif ($count == 1) {
                self::assertSame($frame2, $frame);
            } elseif ($count == 2) {
                self::assertSame($frame1, $frame);
            }
            $count++;
        }
    }



    /**
     * Test that CallStack can detect the last application frame properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_accessing_the_last_application_frame(): void
    {
        $frame1 = self::buildCallStackFrame('/var/www/html/src/some-file1', '/var/www/html', false);
        $frame2 = self::buildCallStackFrame('/var/www/html/src/some-file2', '/var/www/html', false);
        $frame3 = self::buildCallStackFrame('/var/www/html/src/some-file3', '/var/www/html', true);
        $callStack = new CallStack([$frame1, $frame2, $frame3]);
        self::assertSame(2, $callStack->getLastApplicationFrameIndex());
        self::assertSame($frame3, $callStack->getLastApplicationFrame());

        $frame1 = self::buildCallStackFrame('/var/www/html/src/some-file1', '/var/www/html', false);
        $frame2 = self::buildCallStackFrame('/var/www/html/src/some-file2', '/var/www/html', true);
        $frame3 = self::buildCallStackFrame('/var/www/html/vendor/some-file3', '/var/www/html', false);
        $callStack = new CallStack([$frame1, $frame2, $frame3]);
        self::assertSame(1, $callStack->getLastApplicationFrameIndex());
        self::assertSame($frame2, $callStack->getLastApplicationFrame());

        $frame1 = self::buildCallStackFrame('/var/www/html/src/some-file1', '/var/www/html', true);
        $frame2 = self::buildCallStackFrame('/var/www/html/vendor/some-file2', '/var/www/html', false);
        $frame3 = self::buildCallStackFrame('/var/www/html/vendor/some-file3', '/var/www/html', false);
        $callStack = new CallStack([$frame1, $frame2, $frame3]);
        self::assertSame(0, $callStack->getLastApplicationFrameIndex());
        self::assertSame($frame1, $callStack->getLastApplicationFrame());

        $frame1 = self::buildCallStackFrame('/var/www/html/src/some-file1', '/var/www/html', false);
        $frame2 = self::buildCallStackFrame('/var/www/html/vendor/some-file2', '/var/www/html', false);
        $frame3 = self::buildCallStackFrame('/var/www/html/src/some-file3', '/var/www/html', true);
        $callStack = new CallStack([$frame1, $frame2, $frame3]);
        self::assertSame(2, $callStack->getLastApplicationFrameIndex());
        self::assertSame($frame3, $callStack->getLastApplicationFrame());

        $frame1 = self::buildCallStackFrame('/var/www/html/vendor/some-file1', '/var/www/html', false);
        $frame2 = self::buildCallStackFrame('/var/www/html/vendor/some-file2', '/var/www/html', false);
        $frame3 = self::buildCallStackFrame('/var/www/html/vendor/some-file3', '/var/www/html', false);
        $callStack = new CallStack([$frame1, $frame2, $frame3]);
        self::assertSame(null, $callStack->getLastApplicationFrameIndex());
        self::assertSame(null, $callStack->getLastApplicationFrame());
    }



    /**
     * Build a dummy CallStackFrame object.
     *
     * @param string  $file                   The file to use.
     * @param string  $projectRootDir         The project-root-dir to use.
     * @param boolean $isLastApplicationFrame Whether this is the last application frame or not.
     * @return Frame
     */
    private static function buildCallStackFrame(
        string $file = 'some-file',
        string $projectRootDir = '',
        bool $isLastApplicationFrame = false,
    ): Frame {

        $file = (string) str_replace('/', DIRECTORY_SEPARATOR, $file);
        $projectRootDir = str_replace('/', DIRECTORY_SEPARATOR, $projectRootDir);

        $projectFile = Common::resolveProjectFile($file, $projectRootDir);
        $isApplicationFrame = Common::isApplicationFrame($projectFile, $projectRootDir);

        return new Frame(
            [
                'file' => $file,
                'line' => mt_rand(),
            ],
            $projectFile,
            [],
            false,
            true,
            $isApplicationFrame,
            $isLastApplicationFrame,
        );
    }
}
