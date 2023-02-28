<?php

namespace CodeDistortion\Clarity\Support;

/**
 * Methods to help identify the environment the code is running in.
 */
class Environment
{
    /**
     * Work out if the current framework is Laravel.
     *
     * @return boolean
     */
    public static function isLaravel(): bool
    {
        return true;
    }
}
