<?php

namespace CodeDistortion\Clarity\Support\Context\CallStack\MetaData;

/**
 * Represents the last call that application (i.e. non-vendor) code made before the exception was triggered.
 */
class LastApplicationFrameMeta extends Meta
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
