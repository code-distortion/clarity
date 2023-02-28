<?php

namespace CodeDistortion\Clarity\Support\Context\CallStack\MetaData;

/**
 * Represents context details that were added to the callstack.
 */
class ContextMeta extends Meta
{
    /**
     * Constructor.
     *
     * @param mixed[] $frameData   The debug_backtrace frame data.
     * @param string  $projectFile The file's location in relation to the project-root.
     * @param mixed[] $context     The context details.
     */
    public function __construct(
        protected array $frameData,
        protected string $projectFile,
        protected array $context,
    ) {
    }



    /**
     * Get the context details.
     *
     * @return mixed[]
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
