<?php

namespace CodeDistortion\Clarity\Tests\Unit\Support\Context\CallStack\MetaData;

use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ContextMeta;
use CodeDistortion\Clarity\Tests\PHPUnitTestCase;

/**
 * Test the ContextMeta class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ContextMetaTest extends PHPUnitTestCase
{
    /**
     * Test the ContextMeta class.
     *
     * @test
     *
     * @return void
     */
    public static function test_context_meta(): void
    {
        $rand = mt_rand();
        $file = "/var/www/html/path/to/file.$rand.php";
        $projectFile = "/path/to/file.$rand.php";
        $line = $rand;
        $function = "something$rand";
        $class = "someClass$rand";
        $context = ['id' => $rand];

        foreach (['->', '::'] as $type) {

            $frameData = [
                'file' => $file,
                'line' => $line,
                'function' => $function,
                'class' => $class,
                'type' => $type,
            ];

            $meta = new ContextMeta($frameData, $projectFile, $context);

            self::assertSame($file, $meta->getFile());
            self::assertSame($projectFile, $meta->getProjectFile());
            self::assertSame($line, $meta->getLine());
            self::assertSame($function, $meta->getFunction());
            self::assertSame($class, $meta->getClass());
            self::assertSame($type, $meta->getType());
            self::assertSame($context, $meta->getContext());
        }
    }
}
