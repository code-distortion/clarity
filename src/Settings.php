<?php

namespace CodeDistortion\Clarity;

/**
 * Common values, shared throughout Clarity.
 */
abstract class Settings
{
    /** @var string The key used to store the global callbacks inside the framework's service container. */
    public const CONTAINER_KEY_GLOBAL_CALLBACKS = 'code-distortion/clarity/global-callbacks';

    /** @var string The key used to store the MetaCallStack inside the framework's service container. */
    public const CONTAINER_KEY_META_CALL_STACK = 'code-distortion/clarity/meta-call-stack';

    /** @var string The key used to store the Contexts that have been associated with exceptions. */
    public const CONTAINER_KEY_EXCEPTION_CONTEXTS = 'code-distortion/clarity/exception-contexts';



    /** @var string The possible error reporting levels. */
    public const REPORTING_LEVEL_DEBUG = 'debug';
    public const REPORTING_LEVEL_INFO = 'info';
    public const REPORTING_LEVEL_NOTICE = 'notice';
    public const REPORTING_LEVEL_WARNING = 'warning';
    public const REPORTING_LEVEL_ERROR = 'error';
    public const REPORTING_LEVEL_CRITICAL = 'critical';
    public const REPORTING_LEVEL_ALERT = 'alert';
    public const REPORTING_LEVEL_EMERGENCY = 'emergency';

    /** @var string[] The possible log-levels. */
    public const LOG_LEVELS = [
        self::REPORTING_LEVEL_DEBUG,
        self::REPORTING_LEVEL_INFO,
        self::REPORTING_LEVEL_NOTICE,
        self::REPORTING_LEVEL_WARNING,
        self::REPORTING_LEVEL_ERROR,
        self::REPORTING_LEVEL_CRITICAL,
        self::REPORTING_LEVEL_ALERT,
        self::REPORTING_LEVEL_EMERGENCY,
    ];



    // Laravel specific settings

    /** @var string The name of the Clarity config file. */
    public const LARAVEL_CONFIG_NAME = 'code_distortion.clarity';

    /** @var string The config file that gets published. */
    public const LARAVEL_PUBLISHABLE_CONFIG = '/config/config.publishable.php';

    /** @var string The config file that gets used. */
    public const LARAVEL_REAL_CONFIG = '/config/config.publishable.php';
}
