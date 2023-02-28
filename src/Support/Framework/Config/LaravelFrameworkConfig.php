<?php

namespace CodeDistortion\Clarity\Support\Framework\Config;

use CodeDistortion\Clarity\Settings;

/**
 * Interacting with the Laravel's configuration.
 */
class LaravelFrameworkConfig implements FrameworkConfigInterface
{
    /**
     * Retrieve the enabled setting.
     *
     * @return boolean|null
     */
    public static function getEnabled(): ?bool
    {
        return self::pickConfigBoolean(Settings::LARAVEL_CONFIG_NAME . '.enabled');
    }



    /**
     * Retrieve the channels to use when the exception is "known".
     *
     * @return string[]
     */
    public static function getChannelsWhenKnown(): array
    {
        return self::pickConfigStringArray(Settings::LARAVEL_CONFIG_NAME . '.channels.when_known');
    }

    /**
     * Retrieve the default channels to use.
     *
     * @return string[]
     */
    public static function getChannelsWhenNotKnown(): mixed
    {
        return self::pickConfigStringArray(Settings::LARAVEL_CONFIG_NAME . '.channels.when_not_known');
    }

    /**
     * Retrieve the framework's default channels.
     *
     * @return string[]
     */
    public static function getFrameworkDefaultChannels(): mixed
    {
        return self::pickConfigStringArray('logging.default');
    }



    /**
     * Retrieve the log reporting level to use.
     *
     * @return string|null
     */
    public static function getLevel(): ?string
    {
        return self::pickConfigString(Settings::LARAVEL_CONFIG_NAME . '.level');
    }



    /**
     * Retrieve the report setting to use.
     *
     * @return boolean|null
     */
    public static function getReport(): ?bool
    {
        return self::pickConfigBoolean(Settings::LARAVEL_CONFIG_NAME . '.report');
    }



    /**
     * Retrieve the rethrow setting to use.
     *
     * @return boolean|null
     */
    public static function getRethrow(): ?bool
    {
        return self::pickConfigBoolean(Settings::LARAVEL_CONFIG_NAME . '.rethrow');
    }



    /**
     * Retrieve the project-root directory.
     *
     * @return string
     */
    public static function getProjectRoot(): string
    {
        return self::isUsingTestBench()
            ? (string) realpath(base_path('../../../../'))
            : (string) realpath(base_path());
    }



    /**
     * Update the framework's config with new values (used while running tests).
     *
     * Note: For frameworks other than Laravel, the keys will need to be converted from Laravel's keys.
     *
     * @param mixed[] $values The values to store.
     * @return void
     */
    public static function updateConfig(array $values): void
    {
        config($values);
    }



    /**
     * Pick a string from Laravel's config.
     *
     * @param string $key The key to look for.
     * @return boolean|null
     */
    private static function pickConfigBoolean(string $key): ?bool
    {
        $value = config($key);
        return (is_bool($value))
            ? $value
            : null;
    }

    /**
     * Pick a string from Laravel's config.
     *
     * @param string $key The key to look for.
     * @return string|null
     */
    private static function pickConfigString(string $key): ?string
    {
        $value = config($key);
        return (is_string($value)) && (mb_strlen($value))
            ? $value
            : null;
    }

    /**
     * Pick a string or array of strings from Laravel's config. Returns them as an array.
     *
     * @param string $key The key to look for.
     * @return string[]
     */
    private static function pickConfigStringArray(string $key): array
    {
        $values = config($key);
        $values = (is_string($values)) || (is_array($values))
            ? $values
            : [];
        return is_array($values)
            ? $values
            : [$values];
    }

    /**
     * Work out if Orchestra Testbench is being used.
     *
     * @return boolean
     */
    private static function isUsingTestBench(): bool
    {
        $testBenchDir = '/vendor/orchestra/testbench-core/laravel';
        $testBenchDir = str_replace('/', DIRECTORY_SEPARATOR, $testBenchDir);
        return mb_substr(base_path(), - mb_strlen($testBenchDir)) == $testBenchDir;
    }
}
