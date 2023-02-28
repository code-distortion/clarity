# Clarity - Understand your exceptions better

[![Latest Version on Packagist](https://img.shields.io/packagist/v/code-distortion/clarity.svg?style=flat-square)](https://packagist.org/packages/code-distortion/clarity)
![PHP Version](https://img.shields.io/badge/PHP-8.0%20to%208.2-blue?style=flat-square)
![Laravel](https://img.shields.io/badge/laravel-8%20to%2010-blue?style=flat-square)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/code-distortion/clarity/run-tests.yml?branch=master&style=flat-square)](https://github.com/code-distortion/clarity/actions)
[![Buy The World a Tree](https://img.shields.io/badge/treeware-%F0%9F%8C%B3-lightgreen?style=flat-square)](https://plant.treeware.earth/code-distortion/clarity)
[![Contributor Covenant](https://img.shields.io/badge/contributor%20covenant-v2.1%20adopted-ff69b4.svg?style=flat-square)](.github/CODE_OF_CONDUCT.md)

***code-distortion/clarity*** is a Laravel package for the catching and reporting of exceptions, with customisable contextual information.

At its simplest, Clarity is a replacement for `try { … } catch (Throwable $e) { report($e); }` with a fluent interface.

However it gives you a *birds-eye-view* of what your code was doing at the time an exception occurs, by letting you add context details throughout your code. It's collected as your code runs, and when an exception occurs, it's reported along with the exception.

``` php
use CodeDistortion\Clarity\Clarity;

// add context details throughout your code
Clarity::summary('Sending an invoice email')
    ->context(['invoice-id' => $invoice->id])
    ->context(['recipient' => $user->email]);

// then, wrap your code like this to catch + report exceptions 
Clarity::run($callable);
```

```
// [todo] - add output from a package that formats the exception
```



## Table of Contents

- [Installation](#installation)
  - [Config File](#config-file)
- [Basic Usage](#basic-usage)
- [Reporting Exceptions (Logging)](#reporting-exceptions-logging)
  - [The Context Object](#the-context-object)
- [Advanced Usage](#advanced-usage)
  - [Default Values](#default-values)
  - [Log Settings](#log-settings)
    - [Log Channel](#log-channel)
    - [Log Level](#log-level)
    - [Disabling Reporting](#disabling-reporting)
  - [Recording "Known" Issues](#recording-known-issues)
  - [Re-throwing Exceptions](#re-throwing-exceptions)
  - [Callbacks](#callbacks)
    - [Using The Context Object in Callbacks](#using-the-context-object-in-callbacks)
    - [Swallowing Exceptions](#swallowing-exceptions)
    - [Global Callbacks](#global-callbacks)
  - [Catching Selectively](#catching-selectively)
    - [Advanced Catching](#advanced-catching)
  - [Speed / Overhead](#speed-overhead)
  - [Disabling Clarity](#disabling-clarity)
- [Testing](#testing)
- [Changelog](#changelog)
    - [SemVer](#semver)
- [Treeware](#treeware)
- [Contributing](#contributing)
    - [Code of Conduct](#code-of-conduct)
    - [Security](#security)
- [Credits](#credits)
- [License](#license)



## Installation

Install the package via composer:

``` bash
composer require code-distortion/clarity
```



### Config File

Although it's not required, publishing the config file lets you set a few default values.

Use the following command to publish the `config/code_distortion.clarity.php` config file:

``` bash
php artisan vendor:publish --provider="CodeDistortion\Clarity\ServiceProvider" --tag="config"
```



## Basic Usage

First, add context details throughout your code in relevant places. Pick places that would give you the most insight when trying to track down a problem. Add as many as you feel necessary.

Running `Clarity::summary("…")` lets you summarise what's happening in a sentence.

Running `Clarity::context([…])` lets you add specific details with an associative array of values.

> ***Note:*** Don't add sensitive details that you don't want to be logged.

In this example, we have a pseudo *action* that sends an HTTP request to a 3rd-party payment gateway.

``` php
use CodeDistortion\Clarity\Clarity;

class MakePaymentAction
{
    public function handle(CreditCard $creditCard, string $amount): bool
    {
        Clarity::summary('Sending a payment request to <3rd-party-payment-gateway>')
            ->context(['credit-card-id' => $creditCard->id])
            ->context(['amount' => $amount]);
               
        // send an HTTP request to the gateway
        // (this may generate an exception)
        $success = …; // do something
        
        return $success;
    }
}
```

Then get Clarity to run the code, the action from above in this case.

``` php
Clarity::summary('Performing shopping-cart checkout')
       ->context(['shopping-cart-id' => $cart->id])
       ->context(['user-id' => $user->id]);
…
$success = Clarity::run(
    fn() => MakePaymentAction::handle($user->creditCard, $cart->total_cost)
);
```

When an exception occurs, `run(…)` will catch and report it by calling Laravel's [`report()` helper](https://laravel.com/docs/10.x/errors#the-report-helper). The rest of your code will continue afterwards.

> ***Tip:*** You can add context details *before* calling `Clarity::run(…)`, as well as *inside* the code that `run(…)` calls.

> ***Tip:*** [Laravel's *dependency injection*](https://laravel.com/docs/10.x/container#when-to-use-the-container) is used to call your closure. When you type-hint the closure's parameters, Laravel will resolve them for you. e.g.
> ``` php
> use Illuminate\Http\Request;
> 
> Clarity::run(
>     fn(Request $request) => dump($request->fullUrl())
> );
> ```



## Reporting Exceptions (Logging)

> ***Note:*** For the context details to be logged, you will need to update `app/Exceptions/Handler.php` in your Laravel project.

> ***Note:*** **This package doesn't format the exception or context details for logging.** It's considered out-of-scope.
> 
> This package is designed so that this can be written separately.
> 
> If you're writing a package (or would like to handle this work yourself), you can [access the `Context` object](#the-context-object) which contains all the details that can be reported.

> ***Tip:*** **Sister package [todo] is such a package that can format exceptions for you. It takes advantage of the extra context information, and makes logging it really easy.**

Use `Clarity::getContext($e)` inside `app/Exceptions/Handler.php` to access the exception's `CodeDistortion\Clarity\Support\Context` object.

You can then choose how `app/Exceptions/Handler.php` should use its values.

``` php
// app/Exceptions/Handler.php

namespace App\Exceptions;

use CodeDistortion\Clarity\Clarity; // <<<
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    …

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {

            $context = Clarity::getContext($e); // <<<
            // … perform formatting and logging here
        });
    }
}
```



### The Context Object

The `Context` object includes a range of details about the exception, including:

- the callstack / stacktrace (based on `$e->getTrace()`, but with the file/line numbers shifted by one so they make more sense),
- a list of the context details (specified by the application) that were present in the callstack at the time the exception was thrown,
- references to the location where the exception was thrown and caught.

``` php
$context->getException(); // the exception that was caught
$context->getMeta();      // the meta data specified by the application
$context->getChannels();  // the intended channels to log to
$context->getLevel();     // the intended reporting level (debug, info, … emergency)
$context->getKnown();     // the "known" issues associated to the exception

$trace = $context->getTrace();         // the stacktrace frames (most recent at the start)
$callstack = $context->getCallStack(); // the same as the stacktrace, but in reverse

$trace->getLastApplicationFrame();      // get the last application (i.e. non-vendor) frame
$trace->getLastApplicationFrameIndex(); // get the index of the last application frame
$trace->getExceptionThrownFrame();      // get the frame that threw the exception
$trace->getExceptionThrownFrameIndex(); // get the index of the frame that threw the exception
$trace->getExceptionCaughtFrame();      // get the frame that caught the exception
$trace->getExceptionCaughtFrameIndex(); // get the index of the frame that caught the exception
```



<details>
<summary>⚙️ Click for more information about the Context object.</summary>

#### Stacktrace / Callstack &amp; Frames

Retrieve the frames using `$context->getTrace()` or `$context->getCallStack()`. `getTrace()` lists the frames in order from most recent to oldest. `getCallStack()` is the same, except that it lists them in reverse from oldest to newest.

The callstack and stacktrace objects are iterable. The frames have the following methods:

``` php
$trace = $context->getTrace(); // or $context->getCallStack();
foreach ($trace as $frame) {
    $frame->getFile();                // the relevant file
    $frame->getProjectFile();         // the same file, but relative to the project-root's dir
    $frame->getLine();                // the relevant line number
    $frame->getFunction();            // the function or method being run at the time
    $frame->getClass();               // the class being used at the time
    $frame->getObject();              // the object instance being used at the time
    $frame->getType();                // the "type" ("::", "->")
    $frame->getArgs();                // the arguments the function or method was called with
    $frame->getMeta();                // retrieve the Meta objects, see below
    $frame->isApplicationFrame();     // is this an application (i.e. non-vendor) frame?
    $frame->isLastApplicationFrame(); // is this the last application frame (before the exception was thrown)?
    $frame->isVendorFrame();          // is this a vendor frame?
    $frame->exceptionWasThrownHere(); // was the exception thrown by this frame?
    $frame->exceptionWasCaughtHere(); // was the exception caught by this frame?
}
```

> ***Note:*** Some of these methods won't always return. It depends on the circumstance. See [PHP's debug_backtrace method](https://www.php.net/manual/en/function.debug-backtrace.php) for more details.



#### Meta Objects

There are 6 types of Meta objects:
- `SummaryMeta` - when the application called `Clarity::summary(…)` to summarise the situation,
- `ContextMeta` - when the application called `Clarity::context(…)` to add context details,
- `CallMeta` - when Clarity ran some code for the application,
- `LastApplicationFrameMeta` - the location of the last application (i.e. non-vendor) frame.
- `ExceptionThrownMeta` - the location the exception was thrown,
- `ExceptionCaughtMeta` - the location the exception was caught,

Calling `$context->getMeta()` will return all of these in an array. You can also retrieve specific types by passing their classnames:

``` php
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ExceptionCaughtMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\ExceptionThrownMeta;
use CodeDistortion\Clarity\Support\Context\CallStack\MetaData\LastApplicationFrameMeta;

$context->getMeta(); // all types
$context->getMeta(LastApplicationFrameMeta::class); // LastApplicationFrameMeta only
$context->getMeta(ExceptionThrownMeta::class, ExceptionCaughtMeta::class); // etc
```

You can retrieve the following details from the Meta objects:

``` php
// all Meta classes
$meta->getFile();        // the relevant file 
$meta->getProjectFile(); // the same file, but relative to the project-root's dir
$meta->getLine();        // the relevant line number
$meta->getFunction();    // the function or method being run at the time
$meta->getClass();       // the class being used at the time
$meta->getType();        // the "type" ("::", "->")
// SummaryMeta only
$meta->getSummary();     // the summary sentence
// ContextMeta only
$meta->getContext();     // the context array
// CallMeta only
$meta->wasCaughtHere();  // whether the excepton was caught here or not
$meta->getKnown();       // the "known" issues associated to the exception
```

The same Meta objects from above are also associated to each frame, respectively:

``` php
$trace = $context->getTrace(); // or $context->getCallStack();
foreach ($trace as $frame) {
    $frame->getMeta(); // all types
    $frame->getMeta(LastApplicationFrameMeta::class); // etc
}
```
</details>



## Advanced Usage

You can configure how Clarity acts by running `Clarity::prime(…)` and then `->execute()` - instead of using `Clarity::run(…)`.

Add your configuration in-between.



### Default Values

Specify a default value to return when an exception occurs.

``` php
$success = Clarity::prime($callable)->default(false)->run();
```

> ***Tip:*** If the default value is *callable*, Clarity will run it when needed to resolve the value.



### Log Settings

#### Log Channel

Specify which Laravel log-channel you'd like to log to. The possible values come from `config/logging.php` in your project.

You can specify more than one if you'd like.

``` php
Clarity::prime($callable)->channel('slack')->execute();
```

> See [Laravel's documentation about logging](https://laravel.com/docs/10.x/logging#available-channel-drivers) for more information about Log Channels.



#### Log Level

You can specify which reporting level you'd like to use when logging.

``` php
use CodeDistortion\Clarity\Settings;

Clarity::prime($callable)->debug()->execute();
Clarity::prime($callable)->info()->execute();
Clarity::prime($callable)->notice()->execute();
Clarity::prime($callable)->warning()->execute();
Clarity::prime($callable)->error()->execute();
Clarity::prime($callable)->critical()->execute();
Clarity::prime($callable)->alert()->execute();
Clarity::prime($callable)->emergency()->execute();
// or
Clarity::prime($callable)->level(Settings::REPORTING_LEVEL_WARNING)->execute();
```

> See [Laravel's documentation about logging](https://laravel.com/docs/10.x/logging#writing-log-messages) for more information about Log Levels.



#### Disabling Reporting

You can disable the reporting of exceptions altogether. This will stop `report()` from being triggered.

``` php
Clarity::prime($callable)->dontReport()->execute();
Clarity::prime($callable)->report(false)->execute();
```



### Recording "Known" Issues

If you use an issue management system like Jira, you can make a note of the issue/task the exception relates to:

``` php
Clarity::prime($callable)
    ->known('https://company.atlassian.net/browse/ISSUE-1234')
    ->execute();
```



### Re-throwing Exceptions

If you'd like the exceptions to be detected and processed, but re-thrown again afterwards, you can tell Clarity to rethrow them:

``` php
Clarity::prime($callable)->rethrow()->execute();
```



### Callbacks

You can add a custom callback to be run when an exception is caught. This can be used to do something when an exception occurs, or to decide whether to ["swallow" the exception](#swallowing-exceptions) or not.

You can add multiple callbacks if you like.

``` php
use CodeDistortion\Clarity\Support\Context;
use Illuminate\Http\Request;
use Throwable;

$callback = fn(Throwable $e, Context $context, Request $request) => …; // do something

Clarity::prime($callable)->callback($callback)->execute();
```

> ***Tip:*** [Laravel's *dependency injection*](https://laravel.com/docs/10.x/container#when-to-use-the-container) is used to call your callback. Just type-hint your parameters, like in the example above.
>
> Extra parameters you can use are:
> - The exception: when the parameter is named `$e` or `$exception`
> - The context object: when type-hinted with `CodeDistortion\Clarity\Support\Context`



#### Using The Context Object in Callbacks

When you type hint a callback parameter with `CodeDistortion\Clarity\Support\Context`, you'll receive a `Context` object populated with lots of details about the exception.

This is the same [Context object](#the-context-object) that's accessible when reporting an exception in `app/Exceptions/Handler.php`.

As well as reading all the values from the context object (show above), you can update some of its values inside your callback to alter what happens.

``` php
use CodeDistortion\Clarity\Settings;
use CodeDistortion\Clarity\Support\Context;

$callback = function (Context $context) {
    // manage the log channel
    $context->getChannels();
    $context->setChannels(['slack']);
    // manage the log reporting level
    $context->getLevel();
    $context->setLevel(Settings::REPORTING_LEVEL_WARNING);
    $context->debug() … $context->emergency();
    // manage the report setting
    $context->getReport();
    $context->setReport(true);
    // manage the rethrow setting
    $context->getRethrow();
    $context->setRethrow(false);
};
```



#### Swallowing Exceptions

Your callback doesn't need to return a value. However, if it returns `false`, the exception will ***not*** be reported or rethrown. It will be "swallowed".

This will also happen if your callback sets `$context->setReport(false)` *and* `$context->setRethrow(false)`.

> ***Tip:*** Callbacks are run in the order they were specified. Subsequent callbacks won't be called when the exception is swallowed.

``` php
use Illuminate\Http\Request;

$callback = function (Request $request) {

    if ($request->userAgent == 'test-agent') {
        // swallow the exception when the user-agent is 'test-agent'
        return false;
        // or
        $context->setReport(false)->setRethrow(false);
    }
};

Clarity::prime($callable)->callback($callback)->execute();
```



#### Global Callbacks

You can tell Clarity to *always* run a particular callback when an exception occurs by adding a global callback. You can add as many as you need.

These callbacks are run *before* the regular callbacks.

``` php
Clarity::globalCallback($callback);
```

A good place to set one up would be in a service provider. See [Laravel's docs for more information](https://laravel.com/docs/10.x/providers#main-content) about service providers.

``` php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use CodeDistortion\Clarity\Clarity;

class MyServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $callback = …; // do something
        Clarity::globalCallback($callback); // <<<
    }
}
```



### Catching Selectively

You can choose to only catch certain types of exceptions. Other exceptions will be rethrown.

``` php
use DivisionByZeroError;

$success = Clarity::prime($callable)
    ->catch(DivisionByZeroError::class) // only catch this type
    ->run();
```

``` php
$success = Clarity::prime($callable)
    ->match('Undefined variable $a')       // when you specify one or the other of match() and matchRegex()
    ->matchRegex('/^Undefined variable /') // only one of them needs to match the exception message
    ->run();
```

You can specify multiple exception classes, match-strings or regexes. 



#### Advanced Catching

You can choose to do different things depending on which exception is caught.

To do this, configure `CodeDistortion\Clarity\CatchType` objects, and pass them to `$clarity->catch()` (instead of 
passing the exception class string).

`CatchType` objects can be customised with most of the same settings as the `Clarity` object. They're all optional, and can be called in any order.

``` php
use CodeDistortion\Clarity\CatchType;
use CodeDistortion\Clarity\Settings;

$catchType1 = CatchType::catch(DivisionByZeroError::class)
    ->match('Undefined variable $a')
    ->matchRegex('/^Undefined variable /')
    ->callback($callback)
    ->known('https://company.atlassian.net/browse/ISSUE-1234')
    ->channel('slack')
    ->level(Settings::REPORTING_LEVEL_WARNING)
    ->debug() … ->emergency()
    ->report() or ->dontReport()
    ->rethrow() or ->dontRethrow()
    ->default('failed');
$catchType2 = …;

Clarity::prime($callable)
    ->catch($catchType1)
    ->catch($catchType2)
    ->execute();
```

CatchTypes are checked in the order they were specified. The first one that matches the exception is used.



## Speed / Overhead

If you're interested to know the amount of overhead needed for Clarity to run, here's a test you can try.

Two pieces of context are added, and then some code is called.

In this example, the code being called is very minimal (it simply returns *true*). In real situations, this would do something useful (like run database queries, perform business-logic, HTTP requests etc), which you would want to balance against this overhead.

No exception is thrown here, which is what will happen most of the time.

``` php
for ($count = 0; $count < 10; $count++) {

    $start = microtime(true);

    Clarity::prime(fn() => true)
        ->summary('something')
        ->context(['some-id' => 1])
        ->execute();

    $taken = round((microtime(true) - $start) * 1000, 3);
    dump("{$taken} ms");
}
```

For me, this takes about 0.13 - 0.14ms.

```
"1.228 ms" *
"0.203 ms"
"0.152 ms"
"0.122 ms"
"0.141 ms"
"0.134 ms"
"0.134 ms"
"0.131 ms"
"0.135 ms"
"0.13 ms"

* If you're running this from the command line,
the first iteration will take longer because
there's no op-cache. This happens for all code.
```

Here's another example, when not actually adding context details.

``` php
for ($count = 0; $count < 10; $count++) {

    $start = microtime(true);

    Clarity::run(fn() => 'a');

    $taken = round((microtime(true) - $start) * 1000, 3);
    dump("{$taken} ms");
}
```

When not adding context details, for me, it takes about 0.04ms

```
"1.031 ms" *
"0.065 ms"
"0.048 ms"
"0.041 ms"
"0.039 ms"
"0.037 ms"
"0.036 ms"
"0.037 ms"
"0.043 ms"
"0.039 ms"

* slower initially when op-cache isn't being used
```



## Disabling Clarity

You can turn Clarity off by setting the `enabled` config setting to `false` in `config/code_distortion.clarity.php`.

> ***Note:*** You will need to [publish Clarity's config file](#config-file) first.

Clarity will run like normal, by catching and reporting exceptions. But will save time by not tracking the meta-data your application sets.

``` php
// configs/code_distortion.clarity.php
…
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

    'enabled' => false,
…
```

> ***Warning:*** Your callbacks will still be run, and will be passed a `Context` object. However the stacktrace frames won't contain meta-data objects like they normally do.
> 
> This will cause problems if your callbacks rely on them to make decisions.

When running the two [speed tests from above](#speed-overhead), but with Clarity disabled:

```
config([Settings::LARAVEL_CONFIG_NAME . '.enabled' => false]);

// … run the tests
```

For me, the first takes about 0.05 - 0.06ms.

```
"0.812 ms" *
"0.073 ms"
"0.058 ms"
"0.05 ms"
"0.048 ms"
"0.052 ms"
"0.054 ms"
"0.054 ms"
"0.053 ms"
"0.049 ms"

* slower initially when op-cache isn't being used
```

and the second takes about 0.02ms

```
"0.78 ms" *
"0.025 ms"
"0.018 ms"
"0.016 ms"
"0.015 ms"
"0.019 ms"
"0.016 ms"
"0.015 ms"
"0.015 ms"
"0.016 ms"

* slower initially when op-cache isn't being used
```



## Testing

We aim to have good tests that provide and a high level of coverage.

``` bash
composer test
```



## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.



### SemVer

This library uses [SemVer 2.0.0](https://semver.org/) versioning. This means that changes to `X` indicate a breaking change: `0.0.X`, `0.X.y`, `X.y.z`. When this library changes to version 1.0.0, 2.0.0 and so forth it doesn't indicate that it's necessarily a notable release, it simply indicates that the changes were breaking.



## Treeware

This package is [Treeware](https://treeware.earth). If you use it in production, then we ask that you [**buy the world a tree**](https://plant.treeware.earth/code-distortion/clarity) to thank us for our work. By contributing to the Treeware forest you’ll be creating employment for local families and restoring wildlife habitats.



## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.



### Code of Conduct

Please see [CODE_OF_CONDUCT](.github/CODE_OF_CONDUCT.md) for details.



### Security

If you discover any security related issues, please email tim@code-distortion.net instead of using the issue tracker.



## Credits

- [Tim Chandler](https://github.com/code-distortion)



## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
