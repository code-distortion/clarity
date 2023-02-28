<?php

namespace CodeDistortion\Clarity\Tests\Unit\Support\Context\CallStack\MetaData;

use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ExceptionThrownMeta;
use CodeDistortion\Clarity\Tests\PHPUnitTestCase;

/**
 * Test the ExceptionThrownMeta class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ExceptionThrownMetaTest extends PHPUnitTestCase
{
    /**
     * Test the ExceptionThrownMeta class.
     *
     * @test
     *
     * @return void
     */
    public static function test_exception_thrown_meta(): void
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

            $meta = new ExceptionThrownMeta($frameData, $projectFile);

            self::assertSame($file, $meta->getFile());
            self::assertSame($projectFile, $meta->getProjectFile());
            self::assertSame($line, $meta->getLine());
            self::assertSame($function, $meta->getFunction());
            self::assertSame($class, $meta->getClass());
            self::assertSame($type, $meta->getType());
        }
    }
}
