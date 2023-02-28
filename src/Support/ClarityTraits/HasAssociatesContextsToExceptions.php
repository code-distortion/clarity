<?php

namespace CodeDistortion\Clarity\Support\ClarityTraits;

use CodeDistortion\Clarity\Settings;
use CodeDistortion\Clarity\Support\ContextInterface;
use Throwable;

/**
 * Methods to associate Context objects with exceptions.
 */
trait HasAssociatesContextsToExceptions
{
    use HasFramework;



    /**
     * Associate a Context object to an exception.
     *
     * @param Throwable        $exception The exception to associate to.
     * @param ContextInterface $context   The context to associate.
     * @return void
     */
    private static function rememberExceptionContext(Throwable $exception, ContextInterface $context): void
    {
        $objectId = spl_object_id($exception);

        $contexts = self::xGetGlobalExceptionContexts();
        $contexts[$objectId] = $context;

        self::xSetGlobalExceptionContexts($contexts);
    }

    /**
     * Get an exception's Context object.
     *
     * @param Throwable $exception The exception to associate to.
     * @return ContextInterface|null
     */
    private static function getExceptionContext(Throwable $exception): ?ContextInterface
    {
        $objectId = spl_object_id($exception);

        return self::xGetGlobalExceptionContexts()[$objectId] ?? null;
    }

    /**
     * Get the latest Context object.
     *
     * @return ContextInterface|null
     */
    private static function getLatestExceptionContext(): ?ContextInterface
    {
        $contexts = self::xGetGlobalExceptionContexts();
        $objectIds = array_keys($contexts);
        $objectId = end($objectIds);
        return $contexts[$objectId] ?? null;
    }

    /**
     * Forget an exception's Context.
     *
     * @param Throwable $exception The exception to associate to.
     * @return void
     */
    private static function forgetExceptionContext(Throwable $exception): void
    {
        $objectId = spl_object_id($exception);

        $contexts = self::xGetGlobalExceptionContexts();
        unset($contexts[$objectId]);

        self::xSetGlobalExceptionContexts($contexts);
    }



    /**
     * Get the current exception-Contexts associations from global storage.
     *
     * @return array<integer, ContextInterface>
     */
    private static function xGetGlobalExceptionContexts(): array
    {
        /** @var array<integer, ContextInterface> $return */
        $return = self::resolveDepInjection()->get(Settings::CONTAINER_KEY_EXCEPTION_CONTEXTS, []);
        return $return;
    }

    /**
     * Set the exception-Contexts associations in global storage.
     *
     * @param array<integer, ContextInterface> $contexts The stack of Context objects to store.
     * @return void
     */
    private static function xSetGlobalExceptionContexts(array $contexts): void
    {
        self::resolveDepInjection()->set(Settings::CONTAINER_KEY_EXCEPTION_CONTEXTS, $contexts);
    }
}
