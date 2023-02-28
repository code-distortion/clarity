<?php

namespace CodeDistortion\Clarity\Tests\Unit\Support;

use CodeDistortion\Clarity\CatchType;
use CodeDistortion\Clarity\Exceptions\ClarityInitialisationException;
use CodeDistortion\Clarity\Settings;
use CodeDistortion\Clarity\Support\ClarityTraits\HasFramework;
use CodeDistortion\Clarity\Support\Inspector;
use CodeDistortion\Clarity\Tests\LaravelTestCase;
use CodeDistortion\Clarity\Tests\Support\MethodCalls;
use Exception;

/**
 * Test configuration related interactions.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ConfigTest extends LaravelTestCase
{
    use HasFramework;



    /**
     * Test that the config values are picked up properly.
     *
     * @test
     * @dataProvider configDataProvider
     *
     * @param array<string, mixed> $config                Config values to set.
     * @param MethodCalls          $initMethodCalls       Methods to call when initialising the CatchType object.
     * @param MethodCalls          $fallbackCalls         Methods to call when initialising the fallback CatchType
     *                                                    object.
     * @param string[]             $expectedGetChannels   The expected channels.
     * @param string|null          $expectedGetLevel      The expected level.
     * @param boolean              $expectedShouldReport  The expected should-report.
     * @param boolean              $expectedShouldRethrow The expected should-rethrow.
     * @return void
     * @throws Exception When a method doesn't exist when instantiating the CatchType class.
     */
    public static function test_that_config_values_are_used(
        array $config,
        MethodCalls $initMethodCalls,
        MethodCalls $fallbackCalls,
        array $expectedGetChannels,
        ?string $expectedGetLevel,
        bool $expectedShouldReport,
        bool $expectedShouldRethrow,
    ): void {

        self::resolveFrameworkConfig()->updateConfig($config);

        $fallbackCallback = fn() => 'hello';
        $callback = fn() => 'hello';

        $fallbackCatchType = self::buildCatchType($fallbackCalls, $fallbackCallback);
        $catchType = self::buildCatchType($initMethodCalls, $callback);

        $inspector = new Inspector($catchType, $fallbackCatchType);

        self::assertSame($expectedGetChannels, $inspector->resolveChannels());
        self::assertSame($expectedGetLevel, $inspector->resolveLevel());
        self::assertSame($expectedShouldReport, $inspector->shouldReport());
        self::assertSame($expectedShouldRethrow, $inspector->shouldRethrow());
    }

    /**
     * Build a CatchType from InitMethodCalls.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the CatchType object.
     * @param callable    $callback        The exception callback to use.
     * @return CatchType
     * @throws Exception When a method doesn't exist when instantiating the CatchType class.
     */
    private static function buildCatchType(MethodCalls $initMethodCalls, callable $callback): CatchType
    {
        $catchTypeObject = new CatchType();
        foreach ($initMethodCalls->getCalls() as $methodCall) {

            $method = $methodCall->getMethod();
            $args = $methodCall->getArgs();

            // place the exception callback into the args for calls to callback()
            if (($method == 'callback') && ($args[0] ?? null)) {
                $args[0] = $callback;
            }

            $toCall = [$catchTypeObject ?? CatchType::class, $method];
            if (is_callable($toCall)) {
                $catchTypeObject = call_user_func_array($toCall, $args);
            } else {
                throw new Exception("Can't call method $method on class CatchType");
            }
        }
        /** @var CatchType $catchTypeObject */
        return $catchTypeObject;
    }



    /**
     * DataProvider for test_that_config_values_are_used.
     *
     * Provide the different combinations of config values and CatchTypes.
     *
     * @return array<integer, array<string, mixed>>
     */
    public static function configDataProvider(): array
    {
        $return = [];



        $channelsWhenKnownCombinations = [
            'known-channel',
            null,
        ];

        $channelsWhenNotKnownCombinations = [
            'default-channel',
            null,
        ];

        $catchTypeMethodCombinations = [
            MethodCalls::add('channel', ['catch-type-channel']),
            MethodCalls::add('channel', ['catch-type-channel'])->add('known', ['a']),
            MethodCalls::new(),
            MethodCalls::new()->add('known', ['a']),
        ];

        $fallbackCatchTypeCombinations = [
            MethodCalls::add('channel', ['fallback-catch-type-channel']),
            MethodCalls::add('channel', ['fallback-catch-type-channel'])->add('known', ['a']),
            MethodCalls::new(),
            MethodCalls::new()->add('known', ['a']),
        ];

        foreach ($channelsWhenKnownCombinations as $whenKnown) {
            foreach ($channelsWhenNotKnownCombinations as $whenNotKnown) {
                foreach ($catchTypeMethodCombinations as $initMethodCalls) {
                    foreach ($fallbackCatchTypeCombinations as $fallbackCalls) {

                        $config = [
                            Settings::LARAVEL_CONFIG_NAME . ".channels.when_known" => $whenKnown,
                            Settings::LARAVEL_CONFIG_NAME . ".channels.when_not_known" => $whenNotKnown,
                            Settings::LARAVEL_CONFIG_NAME . ".level" => 'debug',
                            Settings::LARAVEL_CONFIG_NAME . ".report" => true,
                            Settings::LARAVEL_CONFIG_NAME . ".rethrow" => false,
                            'logging.default' => 'stack'
                        ];

                        $return[] = self::buildParams($config, $initMethodCalls, $fallbackCalls);
                    }
                }
            }
        }



        $levelCombinations = [
            'debug',
        ];

        $catchTypeMethodCombinations = [
            MethodCalls::add('level', ['info']),
            MethodCalls::new(),
        ];

        $fallbackCatchTypeCombinations = [
            MethodCalls::add('level', ['warning']),
            MethodCalls::new(),
        ];

        foreach ($levelCombinations as $level) {
            foreach ($catchTypeMethodCombinations as $initMethodCalls) {
                foreach ($fallbackCatchTypeCombinations as $fallbackCalls) {

                    $config = [
                        Settings::LARAVEL_CONFIG_NAME . ".channels.when_known" => null,
                        Settings::LARAVEL_CONFIG_NAME . ".channels.when_not_known" => null,
                        Settings::LARAVEL_CONFIG_NAME . ".level" => $level,
                        Settings::LARAVEL_CONFIG_NAME . ".report" => true,
                        Settings::LARAVEL_CONFIG_NAME . ".rethrow" => false,
                        'logging.default' => 'stack'
                    ];

                    $return[] = self::buildParams($config, $initMethodCalls, $fallbackCalls);
                }
            }
        }



        $reportCombinations = [
            true,
            false,
        ];

        $catchTypeMethodCombinations = [
            MethodCalls::add('report', [true]),
            MethodCalls::add('report', [false]),
            MethodCalls::new(),
        ];

        $fallbackCatchTypeCombinations = [
            MethodCalls::add('report', [true]),
            MethodCalls::add('report', [false]),
            MethodCalls::new(),
        ];

        foreach ($reportCombinations as $report) {
            foreach ($catchTypeMethodCombinations as $initMethodCalls) {
                foreach ($fallbackCatchTypeCombinations as $fallbackCalls) {

                    $config = [
                        Settings::LARAVEL_CONFIG_NAME . ".channels.when_known" => null,
                        Settings::LARAVEL_CONFIG_NAME . ".channels.when_not_known" => null,
                        Settings::LARAVEL_CONFIG_NAME . ".level" => 'debug',
                        Settings::LARAVEL_CONFIG_NAME . ".report" => $report,
                        Settings::LARAVEL_CONFIG_NAME . ".rethrow" => false,
                        'logging.default' => 'stack'
                    ];

                    $return[] = self::buildParams($config, $initMethodCalls, $fallbackCalls);
                }
            }
        }



        $rethrowCombinations = [
            true,
            false,
        ];

        $catchTypeMethodCombinations = [
            MethodCalls::add('rethrow', [true]),
            MethodCalls::add('rethrow', [false]),
            MethodCalls::new(),
        ];

        $fallbackCatchTypeCombinations = [
            MethodCalls::add('rethrow', [true]),
            MethodCalls::add('rethrow', [false]),
            MethodCalls::new(),
        ];

        foreach ($rethrowCombinations as $rethrow) {
            foreach ($catchTypeMethodCombinations as $initMethodCalls) {
                foreach ($fallbackCatchTypeCombinations as $fallbackCalls) {

                    $config = [
                        Settings::LARAVEL_CONFIG_NAME . ".channels.when_known" => null,
                        Settings::LARAVEL_CONFIG_NAME . ".channels.when_not_known" => null,
                        Settings::LARAVEL_CONFIG_NAME . ".level" => 'debug',
                        Settings::LARAVEL_CONFIG_NAME . ".report" => true,
                        Settings::LARAVEL_CONFIG_NAME . ".rethrow" => $rethrow,
                        'logging.default' => 'stack'
                    ];

                    $return[] = self::buildParams($config, $initMethodCalls, $fallbackCalls);
                }
            }
        }

        return $return;
    }



    /**
     * Determine the parameters to pass to the test_that_config_values_are_used test.
     *
     * @param array<string, mixed> $config          Config values to set.
     * @param MethodCalls          $initMethodCalls Methods to call when initialising the CatchType object.
     * @param MethodCalls          $fallbackCalls   Methods to call when initialising the fallback CatchType object.
     * @return array<string, mixed>
     */
    private static function buildParams(
        array $config,
        MethodCalls $initMethodCalls,
        MethodCalls $fallbackCalls,
    ): array {

        $catchTypeKnown = $initMethodCalls->getAllCallArgsFlat('known');
        $fallbackKnown = $fallbackCalls->getAllCallArgsFlat('known');

        $catchTypeChannels = $initMethodCalls->getAllCallArgsFlat('channel');
        $fallbackChannels = $fallbackCalls->getAllCallArgsFlat('channel');

        $catchTypeLevel = last($initMethodCalls->getAllCallArgsFlat('level'));
        $fallbackLevel = last($fallbackCalls->getAllCallArgsFlat('level'));
        $catchTypeLevel = ($catchTypeLevel === false)
            ? null
            : $catchTypeLevel;
        $fallbackLevel = ($fallbackLevel === false)
            ? null
            : $fallbackLevel;

        $catchTypeReport = null;
        foreach ($initMethodCalls->getCalls(['report', 'dontReport']) as $methodCall) {
            /** @var 'report'|'dontReport' $method */
            $method = $methodCall->getMethod();
            $catchTypeReport = match ($method) {
                'report' => (bool) ($methodCall->getArgs()[0] ?? true),
                'dontReport' => false,
            };
        }

        $fallbackReport = null;
        foreach ($fallbackCalls->getCalls(['report', 'dontReport']) as $methodCall) {
            /** @var 'report'|'dontReport' $method */
            $method = $methodCall->getMethod();
            $fallbackReport = match ($method) {
                'report' => (bool) ($methodCall->getArgs()[0] ?? true),
                'dontReport' => false,
            };
        }

        $catchTypeRethrow = null;
        foreach ($initMethodCalls->getCalls(['rethrow', 'dontRethrow']) as $methodCall) {
            /** @var 'rethrow'|'dontRethrow' $method */
            $method = $methodCall->getMethod();
            $catchTypeRethrow = match ($method) {
                'rethrow' => (bool) ($methodCall->getArgs()[0] ?? true),
                'dontRethrow' => false,
            };
        }

        $fallbackRethrow = null;
        foreach ($fallbackCalls->getCalls(['rethrow', 'dontRethrow']) as $methodCall) {
            /** @var 'rethrow'|'dontRethrow' $method */
            $method = $methodCall->getMethod();
            $fallbackRethrow = match ($method) {
                'rethrow' => (bool) ($methodCall->getArgs()[0] ?? true),
                'dontRethrow' => false,
            };
        }



        if ((count($catchTypeKnown)) || (count($fallbackKnown))) {
            $expectedGetChannels = $catchTypeChannels
                ?: $fallbackChannels
                ?: $config[Settings::LARAVEL_CONFIG_NAME . ".channels.when_known"]
                ?? $config[Settings::LARAVEL_CONFIG_NAME . ".channels.when_not_known"]
                ?? $config['logging.default'];
        } else {
            $expectedGetChannels = $catchTypeChannels
                ?: $fallbackChannels
                ?: $config[Settings::LARAVEL_CONFIG_NAME . ".channels.when_not_known"]
                ?? $config['logging.default'];
        }
        $expectedGetChannels = is_array($expectedGetChannels)
            ? $expectedGetChannels
            : [$expectedGetChannels];

        $expectedGetLevel = $catchTypeLevel
            ?: $fallbackLevel
            ?: $config[Settings::LARAVEL_CONFIG_NAME . '.level'];

        $expectedShouldReport = $catchTypeReport
            ?? $fallbackReport
            ?? $config[Settings::LARAVEL_CONFIG_NAME . '.report']
            ?? true; // default true

        $expectedShouldRethrow = $catchTypeRethrow
            ?? $fallbackRethrow
            ?? $config[Settings::LARAVEL_CONFIG_NAME . '.rethrow']
            ?? false; // default false



        return [
            'config' => $config,
            'initMethodCalls' => $initMethodCalls,
            'fallbackInitMethodCalls' => $fallbackCalls,
            'expectedGetChannels' => $expectedGetChannels,
            'expectedGetLevel' => $expectedGetLevel,
            'expectedShouldReport' => $expectedShouldReport,
            'expectedShouldRethrow' => $expectedShouldRethrow,
        ];
    }



    /**
     * Test that the project-root-directory is detected properly.
     *
     * @test
     *
     * @return void
     * @throws ClarityInitialisationException When the framework can't be resolved.
     */
    public static function test_project_root_dir_detection(): void
    {
        self::assertSame(
            (string) realpath(__DIR__ . '/../../../'),
            self::resolveFrameworkConfig()->getProjectRoot()
        );
    }



    /**
     * Test that an invalid config "level" value will trigger an exception when accessed.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_invalid_config_level_triggers_an_exception(): void
    {
        self::resolveFrameworkConfig()->updateConfig([Settings::LARAVEL_CONFIG_NAME . '.level' => 'INVALID']);

        $inspector = new Inspector(new CatchType());

        $exceptionWasThrown = false;
        try {
            $inspector->resolveLevel();
        } catch (ClarityInitialisationException) {
            $exceptionWasThrown = true;
        }

        self::assertTrue($exceptionWasThrown);
    }
}
