<?php

namespace CodeDistortion\Clarity\Support\Context\CallStack\MetaData;

/**
 * Represents where the exception was caught.
 */
class ExceptionCaughtMeta extends Meta
{
    /**
     * Constructor.
     *
     * @param mixed[] $frameData   The debug_backtrace frame data.
     * @param string  $projectFile The file's location in relation to the project-root.
     */
    public function __construct(
        protected array $frameData,
        protected string $projectFile,
    ) {
    }
}
