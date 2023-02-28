<?php

namespace CodeDistortion\Clarity\Support\Context\CallStack\MetaData;

/**
 * Contains information about some meta-data that was added to the callstack.
 */
abstract class Meta
{
    /** @var mixed[] The stacktrace frame. */
    protected array $frameData;

    /** @var string The file that made the call, relative to the project-root. */
    protected string $projectFile;







    /**
     * Get the frame's file.
     *
     * @return string
     */
    public function getFile(): string
    {
        /** @var string */
        return $this->frameData['file'] ?? '';
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
        return $this->frameData['line'] ?? 0;
    }

    /**
     * Get the frame's function.
     *
     * @return string
     */
    public function getFunction(): string
    {
        /** @var string */
        return $this->frameData['function'] ?? '';
    }

    /**
     * Get the frame's class.
     *
     * @return string
     */
    public function getClass(): string
    {
        /** @var string */
        return $this->frameData['class'] ?? '';
    }

//    /**
//     * Get the frame's object.
//     *
//     * @return object|null
//     */
//    public function getObject(): ?object
//    {
//        /** @var object|null */
//        return $this->frameData['object'] ?? null;
//    }

    /**
     * Get the frame's type.
     *
     * @return string
     */
    public function getType(): string
    {
        /** @var string */
        return $this->frameData['type'] ?? '';
    }

//    /**
//     * Get the frame's args.
//     *
//     * @return mixed[]|null
//     */
//    public function getArgs(): ?array
//    {
//        /** @var mixed[]|null */
//        return $this->frame['args'] ?? null;
//    }
}
