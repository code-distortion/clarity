<?php

namespace CodeDistortion\Clarity\Exceptions;

use CodeDistortion\Clarity\Settings;

/**
 * Exception generated when initialising Clarity.
 */
class ClarityInitialisationException extends ClarityException
{
    /**
     * The current framework type cannot be resolved.
     *
     * @return self
     */
    public static function unknownFramework(): self
    {
        return new self("The current framework type could not be resolved");
    }

    /**
     * An invalid level was specified.
     *
     * @param string|null $level The invalid level.
     * @return self
     */
    public static function levelNotAllowed(?string $level): self
    {
        return new self("Level \"$level\" is not allowed. Please choose from: " . implode(', ', Settings::LOG_LEVELS));
    }

    /**
     * The caller must call prime() before calling the other methods like known() and catch().
     *
     * @param string $method The method that was called.
     * @return self
     */
    public static function runPrimeFirst(string $method): self
    {
        return new self("Please call prime(…) first before calling $method()");
    }

    /**
     * Invalid meta-data was added to the MetaCallStack, and can't be used.
     *
     * @param string $type The invalid meta type.
     * @return self
     */
    public static function invalidMetaType(string $type): self
    {
        return new self("Invalid meta type \"$type\"");
    }
}
