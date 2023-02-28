<?php

namespace CodeDistortion\Clarity\Support\ClarityTraits;

use CodeDistortion\Clarity\Settings;
use CodeDistortion\Clarity\Support\MetaCallStack;
use CodeDistortion\Staticall\Staticall;

/**
 * Methods to interact with a global MetaCallStack object.
 *
 * @codingStandardsIgnoreStart
 *
 * @method self summary(string $summary) Specify a summary of the current situation.
 * @method static self summary(string $summary) Specify a summary of the current situation.
 * @method self context(mixed[] $context) Specify context details about the current situation.
 * @method static self context(mixed[] $context) Specify context details about the current situation.
 *
 * @codingStandardsIgnoreEnd
 */
trait HasMetaCallStack
{
    use HasFramework;
    use Staticall;



    /**
     * Specify the situation summary to report.
     *
     * @param string $summary A summary of the situation.
     * @return $this
     */
    protected function callSummary(string $summary): static
    {
        if (!self::resolveFrameworkConfig()->getEnabled()) {
            return $this;
        }

        self::getGlobalMetaCallStack()->pushMetaData('summary', $summary, 3);

        return $this;
    }



    /**
     * Specify the context to report.
     *
     * @param mixed[] $context Context details about the current situation.
     * @return $this
     */
    protected function callContext(array $context): static
    {
        if (!self::resolveFrameworkConfig()->getEnabled()) {
            return $this;
        }

        self::getGlobalMetaCallStack()->pushMetaData('context', $context, 3);

        return $this;
    }



    /**
     * Add a info about a Clarity instance to the callstack.
     *
     * @param integer $objectId  The object-id of the Clarity object.
     * @param integer $stepsBack The number of frames to go back, to get the intended caller frame.
     * @return $this
     */
    private function addClarityMetaToCallStack(int $objectId, int $stepsBack): static
    {
        if (!self::resolveFrameworkConfig()->getEnabled()) {
            return $this;
        }

        $clarityMeta = [
            'object-id' => $objectId,
            'known' => [],
        ];

        self::getGlobalMetaCallStack()->pushMetaData('clarity', $clarityMeta, $stepsBack + 1, true);

        return $this;
    }

    /**
     * Update Clarity info with its "known" details (know that they've been resolved).
     *
     * @param integer  $objectId The object-id of the Clarity object.
     * @param string[] $known    The known details.
     * @return $this
     */
    private function addKnownToClarityMeta(int $objectId, array $known): static
    {
        if (!count($known)) {
            return $this;
        }

        if (!self::resolveFrameworkConfig()->getEnabled()) {
            return $this;
        }

        $clarityMeta = [
            'object-id' => $objectId,
            'known' => $known,
        ];

        self::getGlobalMetaCallStack()->replaceMetaDataValue('clarity', 'object-id', $objectId, $clarityMeta);

        return $this;
    }





    /**
     * Get the MetaCallStack from global storage (creates and stores a new one if it hasn't been set yet).
     *
     * @return MetaCallStack
     */
    private static function getGlobalMetaCallStack(): MetaCallStack
    {
        /** @var MetaCallStack $return */
        $return = self::resolveDepInjection()->getOrSet(
            Settings::CONTAINER_KEY_META_CALL_STACK,
            fn() => new MetaCallStack()
        );

        return $return;
    }
}
