<?php

namespace CodeDistortion\Clarity;

use CodeDistortion\Clarity\Exceptions\ClarityInitialisationException;
use CodeDistortion\Clarity\Support\Common;
use CodeDistortion\Staticall\Staticall;

/**
 * Define how exceptions should be caught and dealt with.
 *
 * @codingStandardsIgnoreStart
 *
 * @method self catch(string|string[] $exceptionType, string|string[] ...$exceptionType2) Specify the types of exceptions to catch.
 * @method static self catch(string|string[] $exceptionType, string|string[] ...$exceptionType2) Specify the types of exceptions to catch.
 *
 * @method self match(string|string[] $matches, string|string[] ...$matches2) Specify string/s the exception message must match.
 * @method static self match(string|string[] $matches, string|string[] ...$matches2) Specify string/s the exception message must match.
 *
 * @method self matchRegex(string|string[] $matches, string|string[] ...$matches2) Specify regex string/s the exception message must match.
 * @method static self matchRegex(string|string[] $matches, string|string[] ...$matches2) Specify regex string/s the exception message must match.
 *
 * @method self callback(callable $callback) Specify a callback to run when an exception occurs.
 * @method static self callback(callable $callback) Specify a callback to run when an exception occurs.
 *
 * @method self callbacks(callable|callable[] $callback, callable|callable[] ...$callback2) Specify callbacks to run when an exception occurs.
 * @method static self callbacks(callable|callable[] $callback, callable|callable[] ...$callback2) Specify callbacks to run when an exception occurs.
 *
 * @method self known(string|string[] $known, string|string[] ...$known2) Specify issue/s that the exception is known to belong to.
 * @method static self known(string|string[] $known, string|string[] ...$known2) Specify issue/s that the exception is known to belong to.
 *
 * @method self channel(string $channel) Specify a channel to log to.
 * @method static self channel(string $channel) Specify a channel to log to.
 *
 * @method self channels(string|string[] $channel, string|string[] ...$channel2) Specify channels to log to.
 * @method static self channels(string|string[] $channel, string|string[] ...$channel2) Specify channels to log to.
 *
 * @method self level(string $level) Specify the log reporting level.
 * @method static self level(string $level) Specify the log reporting level.
 *
 * @method self debug() Set the log reporting level to "debug".
 * @method static self debug() Set the log reporting level to "debug".
 *
 * @method self info() Set the log reporting level to "info".
 * @method static self info() Set the log reporting level to "info".
 *
 * @method self notice() Set the log reporting level to "notice".
 * @method static self notice() Set the log reporting level to "notice".
 *
 * @method self warning() Set the log reporting level to "warning".
 * @method static self warning() Set the log reporting level to "warning".
 *
 * @method self error() Set the log reporting level to "error".
 * @method static self error() Set the log reporting level to "error".
 *
 * @method self critical() Set the log reporting level to "critical".
 * @method static self critical() Set the log reporting level to "critical".
 *
 * @method self alert() Set the log reporting level to "alert".
 * @method static self alert() Set the log reporting level to "alert".
 *
 * @method self emergency() Set the log reporting level to "emergency".
 * @method static self emergency() Set the log reporting level to "emergency".
 *
 * @method self report(boolean $report = true) Specify that exceptions should be reported.
 * @method static self report(boolean $report = true) Specify that exceptions should be reported.
 *
 * @method self dontReport() Specify that exceptions should not be reported.
 * @method static self dontReport() Specify that exceptions should not be reported.
 *
 * @method self rethrow(boolean $rethrow = true) Specify that any caught exceptions should be re-thrown.
 * @method static self rethrow(boolean $rethrow = true) Specify that any caught exceptions should be re-thrown.
 *
 * @method self dontRethrow() Specify that caught exceptions should not be re-thrown.
 * @method static self dontRethrow() Specify that any caught exceptions should not be re-thrown.
 *
 * @method self default(mixed $default = true) Specify the default value to return when an exception occurs.
 * @method static self default(mixed $default = true) Specify the default value to return when an exception occurs.
 *
 * @codingStandardsIgnoreEnd
 */
class CatchType
{
    use Staticall;



    /** @var string[] The types of exceptions to pick up. */
    protected array $exceptionClasses = [];

    /** @var string[] The exception message must match one of these (when set). */
    protected array $matchStrings = [];

    /** @var string[] The exception message must match one of these regexes (when set). */
    protected array $matchRegexes = [];

    /** @var callable[] Callbacks to run when triggered. */
    protected array $callbacks = [];

    /** @var string[] The issues this exception relates to. */
    protected array $known = [];

    /** @var string[] The channels to log to. */
    protected array $channels = [];

    /** @var string|null The log reporting level to use. */
    protected ?string $level = null;

    /** @var boolean|null Whether to report the issue (using the framework's reporting mechanism). */
    protected ?bool $report = null;

    /** @var boolean|null Whether to rethrow the exception. */
    protected ?bool $rethrow = null;

    /** @var boolean Whether the default value has been set or not. */
    protected bool $defaultIsSet = false;

    /** @var mixed The default value to return when an exception occurs. */
    protected mixed $default = null;



    /**
     * Specify the types of exceptions to catch.
     *
     * @param string|string[] $exceptionType     The exception classes to catch.
     * @param string|string[] ...$exceptionType2 The exception classes to catch.
     * @return $this
     */
    protected function callCatch(string|array $exceptionType, string|array ...$exceptionType2): self
    {
        /** @var string[] $exceptionClasses */
        $exceptionClasses = Common::normaliseArgs($this->exceptionClasses, func_get_args());
        $this->exceptionClasses = $exceptionClasses;

        return $this;
    }



    /**
     * Specify string/s the exception message must match.
     *
     * @param string|string[] $match     The string/s the exception message needs to match.
     * @param string|string[] ...$match2 The string/s the exception message needs to match.
     * @return $this
     */
    protected function callMatch(string|array $match, string|array ...$match2): self
    {
        /** @var string[] $matchStrings */
        $matchStrings = Common::normaliseArgs($this->matchStrings, func_get_args());
        $this->matchStrings = $matchStrings;

        return $this;
    }



    /**
     * Specify regex string/s the exception message must match.
     *
     * @param string|string[] $match     The regex string/s the exception message needs to match.
     * @param string|string[] ...$match2 The regex string/s the exception message needs to match.
     * @return $this
     */
    protected function callMatchRegex(string|array $match, string|array ...$match2): self
    {
        /** @var string[] $matchRegexes */
        $matchRegexes = Common::normaliseArgs($this->matchRegexes, func_get_args());
        $this->matchRegexes = $matchRegexes;

        return $this;
    }



    /**
     * Specify a callback to run when an exception occurs.
     *
     * @param callable $callback The callback to run.
     * @return $this
     */
    protected function callCallback(callable $callback): self
    {
        $this->callbacks([$callback]);

        return $this;
    }

    /**
     * Specify callbacks to run when an exception occurs.
     *
     * @param callable|callable[] $callback     The callback/s to run.
     * @param callable|callable[] ...$callback2 The callback/s to run.
     * @return $this
     */
    protected function callCallbacks(callable|array $callback, callable|array ...$callback2): self
    {
        /** @var callable[] $callbacks */
        $callbacks = Common::normaliseArgs($this->callbacks, func_get_args());
        $this->callbacks = $callbacks;

        return $this;
    }



    /**
     * Specify issue/s that the exception is known to belong to.
     *
     * @param string|string[] $known     The issue/s this exception is known to belong to.
     * @param string|string[] ...$known2 The issue/s this exception is known to belong to.
     * @return $this
     */
    protected function callKnown(string|array $known, string|array ...$known2): self
    {
        /** @var string[] $known */
        $known = Common::normaliseArgs($this->known, func_get_args());
        $this->known = $known;

        return $this;
    }



    /**
     * Specify a channel to log to.
     *
     * @param string $channel The channel to log to.
     * @return $this
     */
    protected function callChannel(string $channel): self
    {
        $this->channels([$channel]);

        return $this;
    }

    /**
     * Specify channels to log to.
     *
     * @param string|string[] $channel     The channel/s to log to.
     * @param string|string[] ...$channel2 The channel/s to log to.
     * @return $this
     */
    protected function callChannels(string|array $channel, string|array ...$channel2): self
    {
        /** @var string[] $channels */
        $channels = Common::normaliseArgs($this->channels, func_get_args());
        $this->channels = $channels;

        return $this;
    }



    /**
     * Specify the log reporting level.
     *
     * @param string $level The log-level to use.
     * @return $this
     * @throws ClarityInitialisationException When an invalid level is specified.
     */
    protected function callLevel(string $level): self
    {
        if (!in_array($level, Settings::LOG_LEVELS)) {
            throw ClarityInitialisationException::levelNotAllowed($level);
        }

        $this->level = $level;

        return $this;
    }

    /**
     * Set the log reporting level to "debug".
     *
     * @return $this
     */
    protected function callDebug(): self
    {
        $this->level = Settings::REPORTING_LEVEL_DEBUG;

        return $this;
    }

    /**
     * Set the log reporting level to "info".
     *
     * @return $this
     */
    protected function callInfo(): self
    {
        $this->level = Settings::REPORTING_LEVEL_INFO;

        return $this;
    }

    /**
     * Set the log reporting level to "notice".
     *
     * @return $this
     */
    protected function callNotice(): self
    {
        $this->level = Settings::REPORTING_LEVEL_NOTICE;

        return $this;
    }

    /**
     * Set the log reporting level to "warning".
     *
     * @return $this
     */
    protected function callWarning(): self
    {
        $this->level = Settings::REPORTING_LEVEL_WARNING;

        return $this;
    }

    /**
     * Set the log reporting level to "error".
     *
     * @return $this
     */
    protected function callError(): self
    {
        $this->level = Settings::REPORTING_LEVEL_ERROR;

        return $this;
    }

    /**
     * Set the log reporting level to  critical".
     *
     * @return $this
     */
    protected function callCritical(): self
    {
        $this->level = Settings::REPORTING_LEVEL_CRITICAL;

        return $this;
    }

    /**
     * Set the log reporting level to "alert".
     *
     * @return $this
     */
    protected function callAlert(): self
    {
        $this->level = Settings::REPORTING_LEVEL_ALERT;

        return $this;
    }

    /**
     * Set the log reporting level to "emergency".
     *
     * @return $this
     */
    protected function callEmergency(): self
    {
        $this->level = Settings::REPORTING_LEVEL_EMERGENCY;

        return $this;
    }



    /**
     * Specify that exceptions should be reported.
     *
     * @param boolean $report Whether to report exceptions or not.
     * @return $this
     */
    protected function callReport(bool $report = true): self
    {
        $this->report = $report;

        return $this;
    }

    /**
     * Specify that exceptions should not be reported.
     *
     * @return $this
     */
    protected function callDontReport(): self
    {
        $this->report = false;

        return $this;
    }



    /**
     * Specify that caught exceptions should be re-thrown.
     *
     * @param boolean $rethrow Whether to rethrow exceptions or not.
     * @return $this
     */
    protected function callRethrow(bool $rethrow = true): self
    {
        $this->rethrow = $rethrow;

        return $this;
    }

    /**
     * Specify that caught exceptions should not be re-thrown.
     *
     * @return $this
     */
    protected function callDontRethrow(): self
    {
        $this->rethrow = false;

        return $this;
    }



    /**
     * Specify the default value to return when an exception occurs.
     *
     * @param mixed $default The default value to use.
     * @return $this
     */
    protected function callDefault(mixed $default): self
    {
        $this->default = $default;
        $this->defaultIsSet = true;

        return $this;
    }
}
