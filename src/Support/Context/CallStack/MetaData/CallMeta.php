<?php

namespace CodeDistortion\Clarity\Support\Context\CallStack\MetaData;

/**
 * Represents a point where Clarity ran a callable for the caller.
 */
class CallMeta extends Meta
{
    /**
     * Constructor.
     *
     * @param mixed[]  $frameData   The debug_backtrace frame data.
     * @param string   $projectFile The file's location in relation to the project-root.
     * @param boolean  $caughtHere  Whether the exception was caught here or not.
     * @param string[] $known       The "known" details about the exception.
     */
    public function __construct(
        protected array $frameData,
        protected string $projectFile,
        protected bool $caughtHere,
        protected array $known,
    ) {
    }



    /**
     * Find out if the exception was caught here or not.
     *
     * @return boolean
     */
    public function wasCaughtHere(): bool
    {
        return $this->caughtHere;
    }

    /**
     * Get the "known" details about the exception.
     *
     * @return string[]
     */
    public function getKnown(): array
    {
        return $this->known;
    }
}
