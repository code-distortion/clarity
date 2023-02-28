<?php

namespace CodeDistortion\Clarity\Support\Context\CallStack;

use CodeDistortion\Clarity\Support\Common;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\Meta;

/**
 * Contains one callstack frame, along with its meta-data.
 */
class Frame
{
    /**
     * Constructor.
     *
     * @param mixed[] $frame                  The stacktrace frame.
     * @param string  $projectFile            The file that made the call, relative to the project-root.
     * @param Meta[]  $meta                   The meta-data objects linked to the frame.
     * @param boolean $thrownHere             Whether the exception was thrown by this frame or not.
     * @param boolean $caughtHere             Whether the exception was caught by this frame or not.
     * @param boolean $isApplicationFrame     Whether this is an application frame or not.
     * @param boolean $isLastApplicationFrame Whether this is the last application frame or not.
     */
    public function __construct(
        private array $frame,
        private string $projectFile,
        private array $meta,
        private bool $thrownHere,
        private bool $caughtHere,
        private bool $isApplicationFrame,
        private bool $isLastApplicationFrame,
    ) {
    }



    /**
     * Get the frame's file.
     *
     * @return string
     */
    public function getFile(): string
    {
        /** @var string */
        return $this->frame['file'] ?? '';
    }

    /**
     * Get the frame's file, relative to the project-root.
     *
     * @return string
     */
    public function getProjectFile(): string
    {
        /** @var string */
        return $this->projectFile;
    }

    /**
     * Get the frame's line.
     *
     * @return integer
     */
    public function getLine(): int
    {
        /** @var integer */
        return $this->frame['line'] ?? 0;
    }

    /**
     * Get the frame's function.
     *
     * @return string
     */
    public function getFunction(): string
    {
        /** @var string */
        return $this->frame['function'] ?? '';
    }

    /**
     * Get the frame's class.
     *
     * @return string
     */
    public function getClass(): string
    {
        /** @var string */
        return $this->frame['class'] ?? '';
    }

    /**
     * Get the frame's object.
     *
     * @return object|null
     */
    public function getObject(): ?object
    {
        /** @var object|null */
        return $this->frame['object'] ?? null;
    }

    /**
     * Get the frame's type.
     *
     * @return string
     */
    public function getType(): string
    {
        /** @var string */
        return $this->frame['type'] ?? '';
    }

    /**
     * Get the frame's args.
     *
     * @return mixed[]|null
     */
    public function getArgs(): ?array
    {
        /** @var mixed[]|null */
        return $this->frame['args'] ?? null;
    }



    /**
     * Get the meta-data that was defined in this frame.
     *
     * @param string|string[] $class     The type/s of meta-objects to get. Defaults to all meta-objects.
     * @param string|string[] ...$class2 The type/s of meta-objects to get. Defaults to all meta-objects.
     * @return Meta[]
     */
    public function getMeta(string|array $class = null, string|array ...$class2): array
    {
        $classes = Common::normaliseArgs([], func_get_args());
        if (!count($classes)) {
            return $this->meta;
        }

        $matchingMeta = [];
        foreach ($this->meta as $meta) {
            foreach ($classes as $class) {
                if ($meta instanceof $class) {
                    $matchingMeta[] = $meta;
                    break;
                }
            }
        }

        return $matchingMeta;
    }



    /**
     * Find out if this is an application (i.e. non-vendor) frame.
     *
     * @return boolean
     */
    public function isApplicationFrame(): bool
    {
        return $this->isApplicationFrame;
    }

    /**
     * Find out if this is the last application (i.e. non-vendor) frame.
     *
     * @return boolean
     */
    public function isLastApplicationFrame(): bool
    {
        return $this->isLastApplicationFrame;
    }

    /**
     * Find out if this is a frame from the vendor directory.
     *
     * @return boolean|null
     */
    public function isVendorFrame(): ?bool
    {
        return !$this->isApplicationFrame;
    }

    /**
     * Find out if the exception was thrown by this frame or not.
     *
     * @return boolean
     */
    public function exceptionWasThrownHere(): bool
    {
        return $this->thrownHere;
    }

    /**
     * Find out if the exception was caught by this frame or not.
     *
     * @return boolean
     */
    public function exceptionWasCaughtHere(): bool
    {
        return $this->caughtHere;
    }





    /**
     * Build a copy of this CallStackFrame, with an extra Meta object added to it.
     *
     * @param Meta    $newMeta                The new Meta object to add.
     * @param boolean $thrownHere             Whether the exception was thrown by this frame or not.
     * @param boolean $caughtHere             Whether the exception was caught by this frame or not.
     * @param boolean $isLastApplicationFrame Whether this is the last application frame or not.
     * @return self
     */
    public function buildCopyWithExtraMeta(
        Meta $newMeta,
        bool $thrownHere,
        bool $caughtHere,
        bool $isLastApplicationFrame
    ): self {
        return new self(
            $this->frame,
            $this->projectFile,
            array_merge($this->getMeta(), [$newMeta]),
            $this->thrownHere || $thrownHere,
            $this->caughtHere || $caughtHere,
            $this->isApplicationFrame,
            $this->isLastApplicationFrame || $isLastApplicationFrame,
        );
    }

    /**
     * Retrieve the raw debug_backtrace() frame data.
     *
     * @return mixed[]
     */
    public function getRawFrameData(): array
    {
        return $this->frame;
    }
}
