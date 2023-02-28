<?php

namespace CodeDistortion\Clarity\Support;

use CodeDistortion\Clarity\Support\Context\CallStack\CallStack;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\Meta;
use Throwable;

/**
 * Provide contextual details about an exception.
 */
interface ContextInterface
{
    /**
     * Constructor.
     *
     * @param Throwable     $exception              The exception that occurred.
     * @param MetaCallStack $metaCallStack          The MetaCallStack object, which includes context details.
     * @param integer       $catcherClarityObjectId The object-id of the Clarity instance that caught the exception.
     * @param string        $projectRootDir         The project's root directory.
     * @param string[]      $channels               The channels to log to.
     * @param string|null   $level                  The log reporting level to use.
     * @param boolean       $report                 Whether the exception should be reported or not.
     * @param boolean       $rethrow                Whether the exception should be rethrown or not.
     */
    public function __construct(
        Throwable $exception,
        MetaCallStack $metaCallStack,
        int $catcherClarityObjectId,
        string $projectRootDir,
        array $channels,
        ?string $level,
        bool $report,
        bool $rethrow
    );



    /**
     * Get the exception that was thrown.
     *
     * @return Throwable
     */
    public function getException(): Throwable;

    /**
     * Get the callstack.
     *
     * @return CallStack
     */
    public function getCallStack(): CallStack;

    /**
     * Get the stacktrace (same as the callstack, but in reverse).
     *
     * @return CallStack
     */
    public function getTrace(): CallStack;

    /**
     * Get the meta-data.
     *
     * @param string|string[] $class     The type/s of meta-objects to get. Defaults to all meta-objects.
     * @param string|string[] ...$class2 The type/s of meta-objects to get. Defaults to all meta-objects.
     * @return Meta[]
     */
    public function getMeta(string|array $class = null, string|array ...$class2): array;

    /**
     * Get the known issues.
     *
     * @return string[]
     */
    public function getKnown(): array;

    /**
     * Get the channels to log to.
     *
     * @return string[]
     */
    public function getChannels(): array;

    /**
     * Get the channels to log to.
     *
     * @return string|null
     */
    public function getLevel(): ?string;

    /**
     * Check whether this exception should be reported or not.
     *
     * @return boolean
     */
    public function getReport(): bool;

    /**
     * Check whether this exception should be rethrown or not.
     *
     * @return boolean
     */
    public function getRethrow(): bool;



    /**
     * Specify the channels to log to.
     *
     * Note: This replaces any previous channels.
     *
     * @param string|string[] $channel     The channel/s to log to.
     * @param string|string[] ...$channel2 The channel/s to log to.
     * @return $this
     */
    public function setChannels(string|array $channel, string|array ...$channel2): self;

    /**
     * Specify the channels to log to.
     *
     * Note: This replaces the previous level.
     *
     * @param string $level The log-level to use.
     * @return $this
     */
    public function setLevel(string $level): self;

    /**
     * Specify that this exception should be reported (using the framework's reporting mechanism) or not.
     *
     * Note: This replaces the previous report setting.
     *
     * @param boolean $report Whether to report the exception or not.
     * @return $this
     */
    public function setReport(bool $report = true): self;

    /**
     * Specify whether this exception should be re-thrown or not.
     *
     * Note: This replaces the previous rethrow setting.
     *
     * @param boolean $rethrow Whether to rethrow the exception or not.
     * @return $this
     */
    public function setRethrow(bool $rethrow = true): self;
}
