<?php

namespace CodeDistortion\Clarity\Tests\Unit\Support\Context\CallStack\MetaData;

use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ExceptionCaughtMeta;
use CodeDistortion\Clarity\Tests\PHPUnitTestCase;

/**
 * Test the ExceptionCaughtMeta class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ExceptionCaughtMetaTest extends PHPUnitTestCase
{
    /**
     * Test the ExceptionCaughtMeta class.
     *
     * @test
     *
     * @return void
     */
    public static function test_exception_caught_meta(): void
    {
        $rand = mt_rand();
        $file = "/var/www/html/path/to/file.$rand.php";
        $projectFile = "/path/to/file.$rand.php";
        $line = $rand;
        $function = "something$rand";
        $class = "someClass$rand";

        foreach (['->', '::'] as $type) {

            $frameData = [
                'file' => $file,
                'line' => $line,
                'function' => $function,
                'class' => $class,
                'type' => $type,
            ];

            $meta = new ExceptionCaughtMeta($frameData, $projectFile);

            self::assertSame($file, $meta->getFile());
            self::assertSame($projectFile, $meta->getProjectFile());
            self::assertSame($line, $meta->getLine());
            self::assertSame($function, $meta->getFunction());
            self::assertSame($class, $meta->getClass());
            self::assertSame($type, $meta->getType());
        }
    }
}
