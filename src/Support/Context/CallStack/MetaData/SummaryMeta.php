<?php

namespace CodeDistortion\Clarity\Support\Context\CallStack\MetaData;

/**
 * Represents summary text that was added to the callstack.
 */
class SummaryMeta extends Meta
{
    /**
     * Constructor.
     *
     * @param mixed[] $frameData   The debug_backtrace frame data.
     * @param string  $projectFile The file's location in relation to the project-root.
     * @param string  $summary     The situation summary.
     */
    public function __construct(
        protected array $frameData,
        protected string $projectFile,
        protected string $summary,
    ) {
    }



    /**
     * Get the summary text.
     *
     * @return string
     */
    public function getSummary(): string
    {
        return $this->summary;
    }
}
