<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Turn On or Off
     |--------------------------------------------------------------------------
     |
     | When enabled, Clarity will catch and report exceptions, and keep track of
     | the context details your application sets. When turned off, Clarity saves
     | time by not tracking the context details. Operates normally otherwise.
     |
     | boolean
     |
     */

    'enabled' => true,

    /*
     |--------------------------------------------------------------------------
     | Channels
     |--------------------------------------------------------------------------
     |
     | These are the channels that are picked when reporting exceptions. When
     | set to null, Laravel's default setting is picked. These can be
     | overridden when building: Clarity::run(..)->channel('xxx').
     |
     | string / null
     |
     */

    'channels' => [
        'when_known' => null,
        'when_not_known' => null,
     ],

    /*
     |--------------------------------------------------------------------------
     | Reporting Level
     |--------------------------------------------------------------------------
     |
     | The default log reporting level to use when reporting exceptions. See
     | https://laravel.com/docs/10.x/logging#writing-log-messages
     | for more details.
     |
     | string
     |
     */

    'level' => 'error',

    /*
     |--------------------------------------------------------------------------
     | Reporting
     |--------------------------------------------------------------------------
     |
     | Reporting is enabled by default, but can be disabled here if need be.
     | This can be overridden when building the Clarity object:
     | $clarity->report(true/false) or $clarity->dontReport().
     |
     | boolean
     |
     */

    'report' => true,

    /*
     |--------------------------------------------------------------------------
     | Rethrowing
     |--------------------------------------------------------------------------
     |
     | Rethrowing is disabled by default, but can be enabled here if need be.
     | This can be overridden when building the Clarity object:
     | $clarity->rethrow(true/false) or $clarity->dontRethrow().
     |
     | boolean
     |
     */

    'rethrow' => false,

];
