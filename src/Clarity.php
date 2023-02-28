<?php

namespace CodeDistortion\Clarity;

use CodeDistortion\Clarity\Support\ClarityTraits\HasAssociatesContextsToExceptions;
use CodeDistortion\Clarity\Support\ClarityTraits\HasCatchTypes;
use CodeDistortion\Clarity\Support\ClarityTraits\HasFramework;
use CodeDistortion\Clarity\Support\ClarityTraits\HasGlobalCallbacks;
use CodeDistortion\Clarity\Support\ClarityTraits\HasMetaCallStack;
use CodeDistortion\Clarity\Support\Context;
use CodeDistortion\Clarity\Support\ContextInterface;
use CodeDistortion\Clarity\Support\Inspector;
use Throwable;

/**
 * Runs a closure for the caller, catching and reporting exceptions that occur.
 *
 * Lets the caller add context meta-data that's included when the reporting.
 */
class Clarity
{
    use HasAssociatesContextsToExceptions;
    use HasCatchTypes;
    use HasFramework;
    use HasGlobalCallbacks;
    use HasMetaCallStack;



    /** @var callable The callable to run. */
    private $callable;

    /** @var mixed Passed-by-reference, will be updated with the exception that occurred (when relevant). */
    private mixed $exceptionHolder; /** @phpstan-ignore-line stops "$exceptionHolder is never read, only written.". */

    /** @var boolean Whether the caller called run(…) (which runs execute() straight away), or not. */
    private bool $instantiatedUsingRun;



    /**
     * Perform some initialisation.
     *
     * @param callable $callable             The callable to run.
     * @param mixed    $exception            Pass-by-reference parameter that is updated with the exception (when
     *                                       relevant).
     * @param boolean  $instantiatedUsingRun Whether the caller called run (which runs execute() straight away).
     * @return $this
     * @see run()
     * @see prime()
     */
    private function init(callable $callable, mixed &$exception, bool $instantiatedUsingRun): self
    {
        $this->callable =& $callable;

        $exception = null;
        $this->exceptionHolder =& $exception;

        $this->instantiatedUsingRun = $instantiatedUsingRun;

        $this->hasCatchTypesInit();

        return $this;
    }



    /**
     * Run a callable, and catch & report any exceptions (depending on the configuration).
     *
     * @param callable $callable  The callable to run.
     * @param mixed    $exception This pass-by-reference parameter will be updated with the exception (when relevant).
     * @return mixed
     * @throws Throwable Exceptions that weren't supposed to be caught.
     */
    public static function run(callable $callable, mixed &$exception = null): mixed
    {
        return (new self())->init($callable, $exception, true)->execute();
    }



    /**
     * Create a new Clarity instance, and prime it with the callback ready to run when execute() is called.
     *
     * @param callable $callable  The callable to run.
     * @param mixed    $exception This pass-by-reference parameter will be updated with the exception (when relevant).
     * @return self
     * @throws Throwable Exceptions that weren't supposed to be caught.
     */
    public static function prime(callable $callable, mixed &$exception = null): self
    {
        return (new self())->init($callable, $exception, false);
    }



    /**
     * Execute the callable, and catch & report any exceptions (depending on the set-up and configuration).
     *
     * @return mixed
     * @throws Throwable Exceptions that weren't supposed to be caught.
     */
    public function execute(): mixed
    {
        $this->exceptionHolder = null;

        $this->addClarityMetaToCallStack(
            spl_object_id($this),
            $this->instantiatedUsingRun
                ? 2
                : 1
        );

        try {

            return self::resolveDepInjection()->call($this->callable);

        } catch (Throwable $e) {

            // the exception wasn't re-thrown…
            // let the caller access the exception via the variable they passed by reference
            $this->exceptionHolder = $e;

            return $this->processException($e);
        }
    }

    /**
     * Process the exception.
     *
     * @param Throwable $e The exception that occurred.
     * @return mixed
     * @throws Throwable Exceptions that weren't supposed to be caught.
     */
    private function processException(Throwable $e): mixed
    {
        // re-throw the exception if it wasn't supposed to be caught
        $inspector = $this->pickMatchingCatchType($e);
        if (is_null($inspector)) {
            throw $e;
        }

        // update the meta callstack with the "known" details for this Clarity, now that they've been resolved.
        $this->addKnownToClarityMeta(spl_object_id($this), $inspector->resolveKnown());

        $this->runCallbacksReportRethrow($e, $inspector);

        // return the default (if it was set)
        return $this->resolveDefaultValue($inspector);
    }



    /**
     * Gather the callbacks to run.
     *
     * @param Inspector $inspector The catch-type that was matched.
     * @return callable[]
     */
    private function gatherCallbacks(Inspector $inspector): array
    {
        return array_merge($this->getGlobalCallbacks(), $inspector->resolveCallbacks());
    }



    /**
     * Run the callbacks, reporting, and then rethrow the exception if necessary.
     *
     * @param Throwable $e         The exception that occurred.
     * @param Inspector $inspector The catch-type that was matched.
     * @return void
     * @throws Throwable When the exception should be rethrown.
     */
    private function runCallbacksReportRethrow(Throwable $e, Inspector $inspector): void
    {
        // make sure something should happen
        // when these are off, the callbacks aren't run
        if ((!$inspector->shouldReport()) && (!$inspector->shouldRethrow())) {
            return;
        }



        $callbacks = $this->gatherCallbacks($inspector);
        $context = (count($callbacks) || $inspector->shouldReport())
            ? $this->buildContext($e, $inspector)
            : null;

        // don't bother building, storing and using the context
        if (!$context) {
//            $this->runReporting($inspector->shouldReport(), $e);
            $this->runRethrow($inspector->shouldRethrow(), $e);
            return;
        }



        self::rememberExceptionContext($e, $context);
        try {

            $this->runCallbacks($context, $e, $callbacks);
            // the $context may have been updated by the callbacks, so use its values instead of $inspector's
            $this->runReporting($context->getReport(), $e);
            $this->runRethrow($context->getRethrow(), $e);

        } finally {
            self::forgetExceptionContext($e);
        }
    }



    /**
     * Build a context object, ready for reporting.
     *
     * @param Throwable $e         The exception that occurred.
     * @param Inspector $inspector The catch-type that was matched.
     * @return ContextInterface
     */
    private function buildContext(Throwable $e, Inspector $inspector): ContextInterface
    {
        return new Context(
            $e,
            self::getGlobalMetaCallStack(),
            spl_object_id($this),
            self::resolveFrameworkConfig()->getProjectRoot(),
            $inspector->resolveChannels(),
            $inspector->resolveLevel(),
            $inspector->shouldReport(),
            $inspector->shouldRethrow()
        );
    }



    /**
     * Run the callbacks.
     *
     * @param ContextInterface $context   The context to make available to the callbacks.
     * @param Throwable        $e         The exception to report.
     * @param callable[]       $callbacks The callbacks to run.
     * @return void
     */
    private function runCallbacks(ContextInterface $context, Throwable $e, array $callbacks): void
    {
        foreach ($callbacks as $callback) {

            // check if the callbacks should continue to be called
            if ((!$context->getReport()) && (!$context->getRethrow())) {
                return; // don't continue
            }

            $this->runCallback($context, $e, $callback);
        }
    }



    /**
     * Run a callback.
     *
     * @param ContextInterface $context  The context to make available to the callbacks.
     * @param Throwable        $e        The exception to report.
     * @param callable         $callback The callback to run.
     * @return void
     */
    private function runCallback(ContextInterface $context, Throwable $e, callable $callback): void
    {
        $return = self::resolveDepInjection()->call($callback, ['exception' => $e, 'e' => $e]);

        // check if the callback returned false
        if ($return === false) {
            $context->setReport(false)->setRethrow(false);
        }
    }

    /**
     * Report the exception, if needed.
     *
     * @param boolean   $shouldReport Whether the exception should be reported or not.
     * @param Throwable $e            The exception that occurred.
     * @return void
     */
    private function runReporting(bool $shouldReport, Throwable $e): void
    {
        if (!$shouldReport) {
            return;
        }

        report($e);
    }

    /**
     * Rethrow the exception, if needed.
     *
     * @param boolean   $shouldRethrow Whether the exception should be rethrown or not.
     * @param Throwable $e             The exception that occurred.
     * @return void
     * @throws Throwable When the exception should be rethrown.
     */
    private function runRethrow(bool $shouldRethrow, Throwable $e): void
    {
        if (!$shouldRethrow) {
            return;
        }

        throw $e;
    }



    /**
     * Determine the default value to return (because an exception occurred).
     *
     * @param Inspector $inspector The catch-type that was matched.
     * @return mixed
     */
    private function resolveDefaultValue(Inspector $inspector): mixed
    {
        $result = $inspector->getDefault();

        if (!is_callable($result)) {
            return $result;
        }

        return self::resolveDepInjection()->call($result);
    }





    /**
     * Retrieve an exception's context details.
     *
     * @param Throwable|null $exception The exception to fetch details for.
     * @return ContextInterface|null
     */
    public static function getContext(?Throwable $exception = null): ?ContextInterface
    {
        return $exception
            ? self::getExceptionContext($exception)
            : self::getLatestExceptionContext();
    }
}
