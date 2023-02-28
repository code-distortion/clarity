<?php

namespace CodeDistortion\Clarity\Support\Context\CallStack;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use OutOfBoundsException;
use SeekableIterator;

/**
 * Class to navigate through CallStackFrames.
 *
 * @codingStandardsIgnoreStart
 *
 * @implements SeekableIterator<integer, Frame>
 * @implements ArrayAccess<integer, Frame>
 *
 * @codingStandardsIgnoreEnd
 */
class CallStack implements ArrayAccess, Countable, SeekableIterator
{
    /** @var Frame[] The CallStackFrames to use. */
    private array $stack;

    /** @var integer The current iteration position. */
    private int $pos = 0;



    /**
     * Constructor.
     *
     * @param Frame[] $stack The Callstack frames.
     */
    public function __construct(array $stack)
    {
        $this->stack = array_values($stack);
    }



    /**
     * Jump to a position.
     *
     * (SeekableIterator interface).
     *
     * @param integer $offset The offset to use.
     * @return void
     * @throws OutOfBoundsException When the offset is invalid.
     */
    public function seek(int $offset): void
    {
        if (!array_key_exists($offset, $this->stack)) {
            throw new OutOfBoundsException("Position $offset does not exist");
        }

        $this->pos = $offset;
    }

    /**
     * Return the current frame.
     *
     * (SeekableIterator interface).
     *
     * @return Frame|null
     */
    public function current(): ?Frame
    {
        return $this->stack[$this->pos] ?? null;
    }

    /**
     * Retrieve the current key.
     *
     * (SeekableIterator interface).
     *
     * @return integer
     */
    public function key(): int
    {
        return $this->pos;
    }

    /**
     * Move to the next frame
     *
     * (SeekableIterator interface).
     *
     * @return void
     */
    public function next(): void
    {
        $this->pos++;
    }

    /**
     * Jump back to the first frame.
     *
     * (SeekableIterator interface).
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->pos = 0;
    }

    /**
     * Check if the current position is valid.
     *
     * (SeekableIterator interface).
     *
     * @return boolean
     */
    public function valid(): bool
    {
        return $this->offsetExists($this->pos);
    }



    /**
     * Check if a position is valid.
     *
     * (ArrayAccess interface).
     *
     * @param mixed $offset The offset to check.
     * @return boolean
     */
    public function offsetExists(mixed $offset): bool
    {
        $offset = is_int($offset) ? $offset : -1;
        return array_key_exists((int) $offset, $this->stack);
    }

    /**
     * Retrieve the value at a particular position.
     *
     * (ArrayAccess interface).
     *
     * @param mixed $offset The offset to retrieve.
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->stack[$offset];
    }

    /**
     * Set the value at a particular position.
     *
     * (ArrayAccess interface).
     *
     * @param mixed $offset The offset to update.
     * @param mixed $value  The value to set.
     * @return void
     * @throws InvalidArgumentException When an invalid value is given.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$value instanceof Frame) {
            throw new InvalidArgumentException("Invalid value - CallStack cannot store this value");
        }
        $this->stack[$offset] = $value;
    }

    /**
     * Remove the value from a particular position.
     *
     * (ArrayAccess interface).
     *
     * @param mixed $offset The offset to remove.
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->stack[$offset]);
    }



    /**
     * Retrieve the number of frames.
     *
     * (Countable interface).
     *
     * @return integer
     */
    public function count(): int
    {
        return count($this->stack);
    }



    /**
     * Reverse the callstack (so it looks like a backtrace).
     *
     * Resets the current position afterwards.
     *
     * @return $this
     */
    public function reverse(): self
    {
        $this->stack = array_reverse($this->stack);
        $this->rewind();

        return $this;
    }

    /**
     * Retrieve the last application (i.e. non-vendor) frame (before the exception was thrown).
     *
     * @return Frame|null
     */
    public function getLastApplicationFrame(): ?Frame
    {
        $frameIndex = $this->getLastApplicationFrameIndex();
        return !is_null($frameIndex)
            ? $this->stack[$frameIndex]
            : null;
    }

    /**
     * Retrieve the index of the last application (i.e. non-vendor) frame (before the exception was thrown).
     *
     * @return integer|null
     */
    public function getLastApplicationFrameIndex(): ?int
    {
        $indexes = array_keys($this->stack);
        foreach (array_reverse($indexes) as $index) {

            if (!$this->stack[$index]->isLastApplicationFrame()) {
                continue;
            }

            return $index;
        }
        return null;
    }

    /**
     * Retrieve the frame that threw the exception.
     *
     * @return Frame|null
     */
    public function getExceptionThrownFrame(): ?Frame
    {
        $frameIndex = $this->getExceptionThrownFrameIndex();
        return !is_null($frameIndex)
            ? $this->stack[$frameIndex]
            : null;
    }

    /**
     * Retrieve the index of the frame that threw the exception.
     *
     * @return integer|null
     */
    public function getExceptionThrownFrameIndex(): ?int
    {
        $indexes = array_keys($this->stack);
        foreach (array_reverse($indexes) as $index) {

            if (!$this->stack[$index]->exceptionWasThrownHere()) {
                continue;
            }

            return $index;
        }
        return null;
    }

    /**
     * Retrieve the frame that caught the exception.
     *
     * @return Frame|null
     */
    public function getExceptionCaughtFrame(): ?Frame
    {
        $frameIndex = $this->getExceptionCaughtFrameIndex();
        return !is_null($frameIndex)
            ? $this->stack[$frameIndex]
            : null;
    }

    /**
     * Retrieve the index of the frame that caught the exception.
     *
     * @return integer|null
     */
    public function getExceptionCaughtFrameIndex(): ?int
    {
        $indexes = array_keys($this->stack);
        foreach (array_reverse($indexes) as $index) {

            if (!$this->stack[$index]->exceptionWasCaughtHere()) {
                continue;
            }

            return $index;
        }
        return null;
    }
}
