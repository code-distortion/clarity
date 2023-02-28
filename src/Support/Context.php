<?php

namespace CodeDistortion\Clarity\Support;

use CodeDistortion\Clarity\Exceptions\ClarityInitialisationException;
use CodeDistortion\Clarity\Settings;
use CodeDistortion\Clarity\Support\ClarityTraits\HasFramework;
use CodeDistortion\Clarity\Support\Context\CallStack\CallStack;
use CodeDistortion\Clarity\Support\Context\CallStack\Frame;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\CallMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ContextMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ExceptionCaughtMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ExceptionThrownMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\LastApplicationFrameMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\Meta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\SummaryMeta;
use Throwable;

/**
 * Provide context details about an exception.
 */
class Context implements ContextInterface
{
    use HasFramework;



    /** @var integer The version of this Context class - this will only change when the format changes. */
    public const CONTEXT_VERSION = 1;

    /** @var CallStack The CallStack, populated with meta-data. */
    private CallStack $callStack;

    /** @var boolean Whether the callStack has been initialised or not. */
    private bool $callStackInitialised = false;

    /** @var string[] The issue/s the exception is known to belong to. */
    private array $known = [];

    /** @var Meta[] The meta-data that was associated to the exception. */
    private array $meta = [];

    /** @var integer|null Temp storage spot, resolved when building the frames, but applied later. */
    private ?int $lastApplicationFrameIndex = null;

    /** @var integer|null Temp storage spot, resolved when building the frames, but applied later. */
    private ?int $wasCaughtHereFrameIndex = null;



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
        private Throwable $exception,
        private MetaCallStack $metaCallStack,
        private int $catcherClarityObjectId,
        private string $projectRootDir,
        private array $channels,
        private ?string $level,
        private bool $report,
        private bool $rethrow
    ) {
    }



    /**
     * Get the exception that was thrown.
     *
     * @return Throwable
     */
    public function getException(): Throwable
    {
        return $this->exception;
    }

    /**
     * Get the callstack.
     *
     * @return CallStack
     */
    public function getCallStack(): CallStack
    {
        $this->initialiseCallStack();

        return clone $this->callStack;
    }

    /**
     * Get the stacktrace (same as the callstack, but in reverse).
     *
     * @return CallStack
     */
    public function getTrace(): CallStack
    {
        $this->initialiseCallStack();

        $trace = clone $this->callStack;
        $trace->reverse();
        return $trace;
    }

    /**
     * Get the meta-data.
     *
     * @param string|string[] $class     The type/s of meta-objects to get. Defaults to all meta-objects.
     * @param string|string[] ...$class2 The type/s of meta-objects to get. Defaults to all meta-objects.
     * @return Meta[]
     */
    public function getMeta(string|array $class = null, string|array ...$class2): array
    {
        $this->initialiseCallStack();

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
     * Get the known issues.
     *
     * @return string[]
     */
    public function getKnown(): array
    {
        $this->initialiseCallStack();

        return $this->known;
    }

    /**
     * Get the channels to log to.
     *
     * @return string[]
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Get the channels to log to.
     *
     * @return string|null
     */
    public function getLevel(): ?string
    {
        return $this->level;
    }

    /**
     * Check whether this exception should be reported or not.
     *
     * @return boolean
     */
    public function getReport(): bool
    {
        return $this->report;
    }

    /**
     * Check whether this exception should be rethrown or not.
     *
     * @return boolean
     */
    public function getRethrow(): bool
    {
        return $this->rethrow;
    }



    /**
     * Specify the channels to log to.
     *
     * Note: This replaces any previous channels.
     *
     * @param string|string[] $channel     The channel/s to log to.
     * @param string|string[] ...$channel2 The channel/s to log to.
     * @return $this
     */
    public function setChannels(string|array $channel, string|array ...$channel2): self
    {
        /** @var string[] $channels */
        $channels = Common::normaliseArgs([], func_get_args());
        $this->channels = $channels;

        return $this;
    }

    /**
     * Specify the log reporting level.
     *
     * Note: This replaces the previous level.
     *
     * @param string $level The log-level to use.
     * @return $this
     */
    public function setLevel(string $level): self
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Set the log reporting level to "debug".
     *
     * @return $this
     */
    public function debug(): static
    {
        $this->level = Settings::REPORTING_LEVEL_DEBUG;

        return $this;
    }

    /**
     * Set the log reporting level to "info".
     *
     * @return $this
     */
    public function info(): static
    {
        $this->level = Settings::REPORTING_LEVEL_INFO;

        return $this;
    }

    /**
     * Set the log reporting level to "notice".
     *
     * @return $this
     */
    public function notice(): static
    {
        $this->level = Settings::REPORTING_LEVEL_NOTICE;

        return $this;
    }

    /**
     * Set the log reporting level to "warning".
     *
     * @return $this
     */
    public function warning(): static
    {
        $this->level = Settings::REPORTING_LEVEL_WARNING;

        return $this;
    }

    /**
     * Set the log reporting level to "error".
     *
     * @return $this
     */
    public function error(): static
    {
        $this->level = Settings::REPORTING_LEVEL_ERROR;

        return $this;
    }

    /**
     * Set the log reporting level to  critical".
     *
     * @return $this
     */
    public function critical(): static
    {
        $this->level = Settings::REPORTING_LEVEL_CRITICAL;

        return $this;
    }

    /**
     * Set the log reporting level to "alert".
     *
     * @return $this
     */
    public function alert(): static
    {
        $this->level = Settings::REPORTING_LEVEL_ALERT;

        return $this;
    }

    /**
     * Set the log reporting level to "emergency".
     *
     * @return $this
     */
    public function emergency(): static
    {
        $this->level = Settings::REPORTING_LEVEL_EMERGENCY;

        return $this;
    }



    /**
     * Specify that this exception should be reported (using the framework's reporting mechanism) or not.
     *
     * Note: This replaces the previous report setting.
     *
     * @param boolean $report Whether to report the exception or not.
     * @return $this
     */
    public function setReport(bool $report = true): self
    {
        $this->report = $report;

        return $this;
    }

    /**
     * Specify whether this exception should be re-thrown or not.
     *
     * Note: This replaces the previous rethrow setting.
     *
     * @param boolean $rethrow Whether to rethrow the exception or not.
     * @return $this
     */
    public function setRethrow(bool $rethrow = true): self
    {
        $this->rethrow = $rethrow;

        return $this;
    }





    /**
     * Initialise the CallStack object.
     *
     * @return void
     */
    private function initialiseCallStack(): void
    {
        if ($this->callStackInitialised) {
            return;
        }
        $this->callStackInitialised = true;

        $this->buildReportingCallStack();
    }

    /**
     * Build the CallStack object that will made available to the caller.
     *
     * @return void
     */
    private function buildReportingCallStack(): void
    {
        $exceptionCallStack = $this->getExceptionCallStack($this->exception);

        $this->metaCallStack->pruneBasedOnExceptionCallStack($exceptionCallStack);

        $this->buildNewCallStack($exceptionCallStack);
    }



    /**
     * Build the exception's callstack. Include the exception's location as a frame.
     *
     * NOTE: The files and lines are shifted by 1, so they make more sense.
     *
     * @param Throwable $e The exception to use.
     * @return array<integer, mixed[]>
     */
    private function getExceptionCallStack(Throwable $e): array
    {
        // shift the file and line values by 1 frame
        $file = $e->getFile();
        $line = $e->getLine();

        $exceptionCallStack = [];
        foreach ($e->getTrace() as $frame) {

            $nextFile = $frame['file'] ?? '';
            $nextLine = $frame['line'] ?? 0;

            $frame['file'] = $file;
            $frame['line'] = $line;
            $exceptionCallStack[] = $frame;

            $file = $nextFile;
            $line = $nextLine;
        }

        $exceptionCallStack[] = [
            'file' => $file,
            'line' => $line,
            'function' => '[top]',
            'args' => [],
        ];

        return array_reverse($exceptionCallStack);

//        // don't shift the file and line values by 1 frame
//        $exceptionCallStack = array_reverse($e->getTrace());
//
//        // add the exception's location as the last frame
//        $exceptionCallStack[] = [
//            'file' => $e->getFile(),
//            'line' => $e->getLine(),
//        ];
//
//        return $exceptionCallStack;
    }



    /**
     * Build a new CallStack, prepared with the correct frames and Meta objects.
     *
     * @param array<integer, mixed[]> $exceptionCallStack The exception's callstack.
     * @return void
     */
    private function buildNewCallStack(array $exceptionCallStack): void
    {
        $this->callStack = new CallStack(
            $this->buildStackFrames($exceptionCallStack)
        );

        if (!self::resolveFrameworkConfig()->getEnabled()) {
            return;
        }

        $this->insertLastApplicationFrameMeta();
        $this->insertExceptionThrownMeta();
        $this->insertExceptionCaughtMeta();

        $this->collectAllMetaObjects();
        $this->collectAllKnownDetails();

        $this->callStack->rewind();
    }



    /**
     * Combine the exception trace and meta-data to build the stack-frames.
     *
     * @param array<integer, mixed[]> $exceptionTrace The exception's stack trace.
     * @return Frame[]
     */
    private function buildStackFrames(array $exceptionTrace): array
    {
        $stackMetaData = $this->metaCallStack->getStackMetaData();

        $frames = [];
        foreach ($exceptionTrace as $index => $frame) {

            $metaDataObjects = [];
            $wasCaughtHere = false;
            foreach ($stackMetaData[$index] ?? [] as $metaData) {

                $metaDataObject = $this->buildMetaObject($metaData);
                $metaDataObjects[] = $metaDataObject;

                if ($metaDataObject instanceof CallMeta) {
                    $wasCaughtHere = $wasCaughtHere || $metaDataObject->wasCaughtHere();
                }
            }

            $file = is_string($frame['file'] ?? null) ? $frame['file'] : '';
            $projectFile = Common::resolveProjectFile($file, $this->projectRootDir);
            $isApplicationFrame = Common::isApplicationFrame($projectFile, $this->projectRootDir);

            $frames[] = new Frame(
                $frame,
                $projectFile,
                $metaDataObjects,
                false,
                $wasCaughtHere,
                $isApplicationFrame,
                false,
            );

            if ($isApplicationFrame) {
                $this->lastApplicationFrameIndex = $index; // store for later when applying the LastApplicationFrameMeta
            }
            if ($wasCaughtHere) {
                $this->wasCaughtHereFrameIndex = $index; // store for later when applying the ExceptionCaughtMeta
            }
        }

        return $frames;
    }

    /**
     * Build a Meta object from the meta-data stored in the MetaCallStack object.
     *
     * @param array<string, mixed> $metaData The meta-data stored in the MetaCallStack object.
     * @return Meta
     * @throws ClarityInitialisationException When the meta-data's type is invalid.
     */
    private function buildMetaObject(array $metaData): Meta
    {
        /** @var string $type */
        $type = $metaData['type'] ?? '';

        /** @var mixed[] $frameData */
        $frameData = $metaData['frame'];

        /** @var string $file */
        $file = $frameData['file'] ?? '';

        $projectFile = Common::resolveProjectFile($file, $this->projectRootDir);

        switch ($type) {

            case 'summary':
                /** @var string $summary */
                $summary = $metaData['value'] ?? '';

                return new SummaryMeta($frameData, $projectFile, $summary);

            case 'context':
                /** @var string[] $context */
                $context = $metaData['value'] ?? [];

                return new ContextMeta($frameData, $projectFile, $context);

            case 'clarity':
                /** @var array<string, mixed> $value */
                $value = $metaData['value'] ?? [];
                /** @var string[] $known */
                $known = $value['known'] ?? [];
                $objectId = $value['object-id'] ?? null;
                $caughtHere = ($objectId == $this->catcherClarityObjectId);

                return new CallMeta($frameData, $projectFile, $caughtHere, $known);

            default:
                throw ClarityInitialisationException::invalidMetaType($type);
        }
    }





    /**
     * Mark last application (i.e. non-vendor) frame with a LastApplicationFrameMeta.
     *
     * @return void
     */
    private function insertLastApplicationFrameMeta(): void
    {
        $frameIndex = $this->lastApplicationFrameIndex;
        if (is_null($frameIndex)) {
            return;
        }

        /** @var Frame $frame */
        $frame = $this->callStack[$frameIndex];

        $meta = new LastApplicationFrameMeta($frame->getRawFrameData(), $frame->getProjectFile());

        $this->callStack[$frameIndex] = $frame->buildCopyWithExtraMeta($meta, false, false, true);
    }

    /**
     * Mark the frame that threw the exception with a ExceptionThrownMeta.
     *
     * @return void
     */
    private function insertExceptionThrownMeta(): void
    {
        $frameIndex = count($this->callStack) - 1; // pick the last frame

        /** @var Frame $frame */
        $frame = $this->callStack[$frameIndex];

        $meta = new ExceptionThrownMeta($frame->getRawFrameData(), $frame->getProjectFile());

        $this->callStack[$frameIndex] = $frame->buildCopyWithExtraMeta($meta, true, false, false);
    }

    /**
     * Mark the frame that caught the exception with a ExceptionCaughtMeta.
     *
     * @return void
     */
    private function insertExceptionCaughtMeta(): void
    {
        $frameIndex = $this->wasCaughtHereFrameIndex;
        if (is_null($frameIndex)) {
            return;
        }

        /** @var Frame $frame */
        $frame = $this->callStack[$frameIndex];

        $meta = new ExceptionCaughtMeta($frame->getRawFrameData(), $frame->getProjectFile());

        $this->callStack[$frameIndex] = $frame->buildCopyWithExtraMeta($meta, false, true, false);
    }





    /**
     * Loop through the callstack frames, pick out the Meta objects, and store for later.
     *
     * @return void
     */
    private function collectAllMetaObjects(): void
    {
        $allMetaObjects = [];
        foreach ($this->callStack as $frame) {
            $allMetaObjects = array_merge($allMetaObjects, $frame->getMeta());
        }
        $this->meta = $allMetaObjects;
    }

    /**
     * Loop through the Meta objects, pick out the "known" details, and store for later.
     *
     * @return void
     */
    private function collectAllKnownDetails(): void
    {
        $known = [];
        /** @var CallMeta $meta */
        foreach ($this->getMeta(CallMeta::class) as $meta) {
            $known = array_merge($known, $meta->getKnown());
        }

        $this->known = $known;
    }
}
