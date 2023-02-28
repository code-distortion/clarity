<?php

namespace CodeDistortion\Clarity\Support\ClarityTraits;

use CodeDistortion\Clarity\CatchType;
use CodeDistortion\Clarity\Exceptions\ClarityInitialisationException;
use CodeDistortion\Clarity\Settings;
use CodeDistortion\Clarity\Support\Common;
use CodeDistortion\Clarity\Support\Inspector;
use Throwable;

/**
 * Methods to populate CatchType objects.
 */
trait HasCatchTypes
{
    /** @var CatchType The default catch-type to use. */
    private CatchType $fallbackCatchType;

    /** @var CatchType[] The catch-types that have been defined explicitly. */
    private array $catchTypes = [];

    /** @var boolean Has this has been initialised? (essentially whether the caller called prime() / run() or not). */
    private bool $hasBeenInitialised = false;



    /**
     * Initialisation, for things in this trait.
     *
     * @return void
     */
    private function hasCatchTypesInit(): void
    {
        $this->fallbackCatchType = new CatchType();

        $this->hasBeenInitialised = true;
    }

    /**
     * Check to make sure this object has been initialised first.
     *
     * (essentially whether the caller called prime() / run() or not).
     *
     * @param string $method The method that was called.
     * @return void
     * @throws ClarityInitialisationException When the caller didn't call prime() or run() first.
     */
    private function checkForInitialisation(string $method): void
    {
        if ($this->hasBeenInitialised) {
            return;
        }

        throw ClarityInitialisationException::runPrimeFirst($method);
    }



    /**
     * Specify the types of exceptions to catch.
     *
     * @param string|CatchType|array<int, string|CatchType> $exceptionType     The exception classes to catch.
     * @param string|CatchType|array<int, string|CatchType> ...$exceptionType2 The exception classes to catch.
     * @return $this
     */
    public function catch(string|CatchType|array $exceptionType, string|CatchType|array ...$exceptionType2): static
    {
        $this->checkForInitialisation('catch');

        /** @var array<integer, CatchType|string> $exceptionTypes */
        $exceptionTypes = Common::normaliseArgs([], func_get_args());

        foreach ($exceptionTypes as $tempExceptionType) {
            if ($tempExceptionType instanceof CatchType) {
                $this->catchTypes[] = $tempExceptionType;
            } else {
                $this->fallbackCatchType->catch($tempExceptionType);
            }
        }

        return $this;
    }



    /**
     * Specify string/s the exception message must match.
     *
     * @param string|string[] $matches     The string/s the exception message needs to match.
     * @param string|string[] ...$matches2 The string/s the exception message needs to match.
     * @return $this
     */
    public function match(string|array $matches, string|array ...$matches2): static
    {
        $this->checkForInitialisation('match');

        call_user_func_array([$this->fallbackCatchType, 'match'], func_get_args());

        return $this;
    }



    /**
     * Specify regex string/s the exception message must match.
     *
     * @param string|string[] $matches     The regex string/s the exception message needs to match.
     * @param string|string[] ...$matches2 The regex string/s the exception message needs to match.
     * @return $this
     */
    public function matchRegex(string|array $matches, string|array ...$matches2): static
    {
        $this->checkForInitialisation('matchRegex');

        call_user_func_array([$this->fallbackCatchType, 'matchRegex'], func_get_args());

        return $this;
    }



    /**
     * Specify a callback to run when an exception occurs.
     *
     * @param callable $callback The callback to run.
     * @return $this
     */
    public function callback(callable $callback): static
    {
        $this->checkForInitialisation('callback');

        $this->fallbackCatchType->callback($callback);

        return $this;
    }

    /**
     * Specify callbacks to run when an exception occurs.
     *
     * @param callable|callable[] $callbacks     The callback/s to run.
     * @param callable|callable[] ...$callbacks2 The callback/s to run.
     * @return $this
     */
    public function callbacks(callable|array $callbacks, callable|array ...$callbacks2): static
    {
        $this->checkForInitialisation('callbacks');

        call_user_func_array([$this->fallbackCatchType, 'callbacks'], func_get_args());

        return $this;
    }



    /**
     * Specify issue/s that the exception is known to belong to.
     *
     * @param string|string[] $known     The issue/s this exception is known to belong to.
     * @param string|string[] ...$known2 The issue/s this exception is known to belong to.
     * @return $this
     */
    public function known(string|array $known, string|array ...$known2): static
    {
        $this->checkForInitialisation('known');

        call_user_func_array([$this->fallbackCatchType, 'known'], func_get_args());

        return $this;
    }



    /**
     * Specify a channel to log to.
     *
     * @param string $channel The channel to log to.
     * @return $this
     */
    public function channel(string $channel): static
    {
        $this->checkForInitialisation('channel');

        $this->fallbackCatchType->channel($channel);

        return $this;
    }

    /**
     * Specify channels to log to.
     *
     * @param string|string[] $channel     The channel/s to log to.
     * @param string|string[] ...$channel2 The channel/s to log to.
     * @return $this
     */
    public function channels(string|array $channel, string|array ...$channel2): static
    {
        $this->checkForInitialisation('channels');

        call_user_func_array([$this->fallbackCatchType, 'channels'], func_get_args());

        return $this;
    }



    /**
     * Specify the log reporting level.
     *
     * @param string $level The log-level to use.
     * @return $this
     */
    public function level(string $level): static
    {
        $this->checkForInitialisation('level');

        $this->fallbackCatchType->level($level);

        return $this;
    }

    /**
     * Set the log reporting level to "debug".
     *
     * @return $this
     */
    public function debug(): static
    {
        $this->checkForInitialisation('debug');

        $this->fallbackCatchType->level(Settings::REPORTING_LEVEL_DEBUG);

        return $this;
    }

    /**
     * Set the log reporting level to "info".
     *
     * @return $this
     */
    public function info(): static
    {
        $this->checkForInitialisation('info');

        $this->fallbackCatchType->level(Settings::REPORTING_LEVEL_INFO);

        return $this;
    }

    /**
     * Set the log reporting level to "notice".
     *
     * @return $this
     */
    public function notice(): static
    {
        $this->checkForInitialisation('notice');

        $this->fallbackCatchType->level(Settings::REPORTING_LEVEL_NOTICE);

        return $this;
    }

    /**
     * Set the log reporting level to "warning".
     *
     * @return $this
     */
    public function warning(): static
    {
        $this->checkForInitialisation('warning');

        $this->fallbackCatchType->level(Settings::REPORTING_LEVEL_WARNING);

        return $this;
    }

    /**
     * Set the log reporting level to "error".
     *
     * @return $this
     */
    public function error(): static
    {
        $this->checkForInitialisation('error');

        $this->fallbackCatchType->level(Settings::REPORTING_LEVEL_ERROR);

        return $this;
    }

    /**
     * Set the log reporting level to  critical".
     *
     * @return $this
     */
    public function critical(): static
    {
        $this->checkForInitialisation('critical');

        $this->fallbackCatchType->level(Settings::REPORTING_LEVEL_CRITICAL);

        return $this;
    }

    /**
     * Set the log reporting level to "alert".
     *
     * @return $this
     */
    public function alert(): static
    {
        $this->checkForInitialisation('alert');

        $this->fallbackCatchType->level(Settings::REPORTING_LEVEL_ALERT);

        return $this;
    }

    /**
     * Set the log reporting level to "emergency".
     *
     * @return $this
     */
    public function emergency(): static
    {
        $this->checkForInitialisation('emergency');

        $this->fallbackCatchType->level(Settings::REPORTING_LEVEL_EMERGENCY);

        return $this;
    }



    /**
     * Specify that exceptions should be reported (using the framework's reporting mechanism).
     *
     * @param boolean $report Whether to report exceptions or not.
     * @return $this
     */
    public function report(bool $report = true): static
    {
        $this->checkForInitialisation('report');

        $this->fallbackCatchType->report($report);

        return $this;
    }

    /**
     * Specify that exceptions should not be reported.
     *
     * @return $this
     */
    public function dontReport(): static
    {
        $this->checkForInitialisation('dontReport');

        $this->fallbackCatchType->dontReport();

        return $this;
    }



    /**
     * Specify that any caught exceptions should be re-thrown.
     *
     * @param boolean $rethrow Whether to rethrow exceptions or not.
     * @return $this
     */
    public function rethrow(bool $rethrow = true): static
    {
        $this->checkForInitialisation('rethrow');

        $this->fallbackCatchType->rethrow($rethrow);

        return $this;
    }

    /**
     * Specify that any caught exceptions should not be re-thrown.
     *
     * @return $this
     */
    public function dontRethrow(): static
    {
        $this->checkForInitialisation('dontRethrow');

        $this->fallbackCatchType->dontRethrow();

        return $this;
    }



    /**
     * Specify the default value that should be returned when an exception occurs.
     *
     * @param mixed $default The default value to use.
     * @return $this
     */
    public function default(mixed $default): static
    {
        $this->checkForInitialisation('default');

        $this->fallbackCatchType->default($default);

        return $this;
    }



    /**
     * Find the catch-type that matches.
     *
     * @param Throwable $e The exception that occurred.
     * @return Inspector|null
     */
    private function pickMatchingCatchType(Throwable $e): ?Inspector
    {
        foreach ($this->xResolveCatchTypesToCheck() as $catchType) {
            $inspector = new Inspector($catchType, $this->fallbackCatchType);
            if ($inspector->checkForMatch($e)) {
                return $inspector;
            }
        }

        return null;
    }

    /**
     * Determine which CatchTypes to loop through and check.
     *
     * @return CatchType[]
     */
    private function xResolveCatchTypesToCheck(): array
    {
        $inspector = new Inspector($this->fallbackCatchType);

        // skip the fallbackCatchType if there are CatchTypes, and it doesn't specify exception types itself
        if ((count($this->catchTypes)) && (!count($inspector->getExceptionClasses()))) {
            return $this->catchTypes;
        } else {
            return array_merge($this->catchTypes, [$this->fallbackCatchType]);
        }
    }
}
