<?php

namespace CodeDistortion\Clarity\Support\Framework\Config;

/**
 * Interface for interacting with the current framework's configuration.
 */
interface FrameworkConfigInterface
{
    /**
     * Retrieve the enabled setting.
     *
     * @return boolean|null
     */
    public static function getEnabled(): ?bool;



    /**
     * Retrieve the channels to use when the exception is "known".
     *
     * @return string[]
     */
    public static function getChannelsWhenKnown(): array;

    /**
     * Retrieve the default channels to use.
     *
     * @return string[]
     */
    public static function getChannelsWhenNotKnown(): mixed;

    /**
     * Retrieve the framework's default channels.
     *
     * @return string[]
     */
    public static function getFrameworkDefaultChannels(): mixed;



    /**
     * Retrieve the log reporting level to use.
     *
     * @return string|null
     */
    public static function getLevel(): ?string;



    /**
     * Retrieve the report setting to use.
     *
     * @return boolean|null
     */
    public static function getReport(): ?bool;



    /**
     * Retrieve the rethrow setting to use.
     *
     * @return boolean|null
     */
    public static function getRethrow(): ?bool;



    /**
     * Retrieve the project-root directory.
     *
     * @return string
     */
    public static function getProjectRoot(): string;





    /**
     * Update the framework's config with new values (used while running tests).
     *
     * Note: For frameworks other than Laravel, the keys will need to be converted from Laravel's keys.
     *
     * @param mixed[] $values The values to store.
     * @return void
     */
    public static function updateConfig(array $values): void;
}
