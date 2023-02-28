<?php

namespace CodeDistortion\Clarity\Tests\Unit\Support\Context\CallStack\MetaData;

use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\CallMeta;
use CodeDistortion\Clarity\Tests\PHPUnitTestCase;

/**
 * Test the CallMeta class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class CallMetaTest extends PHPUnitTestCase
{
    /**
     * Test the CallMeta class.
     *
     * @test
     *
     * @return void
     */
    public static function test_call_meta(): void
    {
        $rand = mt_rand();
        $file = "/var/www/html/path/to/file.$rand.php";
        $projectFile = "/path/to/file.$rand.php";
        $line = $rand;
        $function = "something$rand";
        $class = "someClass$rand";
        $known = ["known-$rand"];

        foreach ([true, false] as $caughtHere) {
            foreach (['->', '::'] as $type) {

                $frameData = [
                    'file' => $file,
                    'line' => $line,
                    'function' => $function,
                    'class' => $class,
                    'type' => $type,
                ];

                $meta = new CallMeta($frameData, $projectFile, $caughtHere, $known);

                self::assertSame($file, $meta->getFile());
                self::assertSame($projectFile, $meta->getProjectFile());
                self::assertSame($line, $meta->getLine());
                self::assertSame($function, $meta->getFunction());
                self::assertSame($class, $meta->getClass());
                self::assertSame($type, $meta->getType());
                self::assertSame($caughtHere, $meta->wasCaughtHere());
                self::assertSame($known, $meta->getKnown());
            }
        }
    }
}
