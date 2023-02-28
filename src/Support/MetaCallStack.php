<?php

namespace CodeDistortion\Clarity\Support;

/**
 * Keep track of the PHP callstack, and associate context details to particular points in the stack.
 */
class MetaCallStack
{
    /** @var array<integer, mixed[]> The current PHP callstack. */
    private array $callStack = [];

    /** @var array<integer, array<integer, mixed[]>> The meta-data that's been linked to points in the callstack. */
    private array $stackMetaData = [];



    /**
     * Get the callstack's meta-data.
     *
     * @return array<integer, array<integer, mixed[]>>
     */
    public function getStackMetaData(): array
    {
        return $this->stackMetaData;
    }





    /**
     * Add some meta-data to the callstack.
     *
     * @param string  $type         The type of meta-data to save.
     * @param mixed   $value        The value to save.
     * @param integer $stepsBack    The number of frames to go back, to get the intended caller frame.
     * @param boolean $removeOthers Remove other meta records of the same type from the top.
     * @return void
     */
    public function pushMetaData(string $type, mixed $value, int $stepsBack = 0, bool $removeOthers = false): void
    {
        $currentCallstack = $this->getCurrentStack($stepsBack + 1);

        $this->replaceCallStack($currentCallstack);

        if ($removeOthers) {
            $this->removeMetaDataFromTop($type);
        }

        $this->recordMetaData($type, $value, array_pop($currentCallstack) ?? []);
    }

    /**
     * Find particular existing meta-data throughout the callstack, and replace it.
     *
     * @param string  $type     The type of meta-data to update.
     * @param string  $field    The field to check when searching.
     * @param mixed   $find     The value to find when searching.
     * @param mixed[] $newValue The replacement meta-data.
     * @return void
     */
    public function replaceMetaDataValue(string $type, string $field, mixed $find, array $newValue): void
    {
        foreach (array_keys($this->stackMetaData) as $frameIndex) {
            foreach (array_keys($this->stackMetaData[$frameIndex]) as $index) {

                if ($this->stackMetaData[$frameIndex][$index]['type'] != $type) {
                    continue;
                }

                if ($this->stackMetaData[$frameIndex][$index]['value'][$field] !== $find) {
                    continue;
                }

                $this->stackMetaData[$frameIndex][$index]['value'] = $newValue;

                return;
            }
        }
    }

    /**
     * Prune off meta-data based on an exception trace.
     *
     * @param array<integer, mixed[]> $exceptionCallStack The exception trace (callstack).
     * @return void
     */
    public function pruneBasedOnExceptionCallStack(array $exceptionCallStack): void
    {
        $this->pruneOffOldMetaData($exceptionCallStack, ['file', 'line']);
    }





    /**
     * Resolve the current PHP callstack. Then tweak it, so it's in a format that's good for comparing.
     *
     * @param integer $stepsBack The number of frames to go back, to get the intended caller frame.
     * @return array<integer, mixed[]>
     */
    private function getCurrentStack(int $stepsBack): array
    {
        $newStack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);

        for ($count = 0; $count < $stepsBack; $count++) {
            array_shift($newStack);
        }

        // turn objects into spl_object_ids
        // - so we're not unnecessarily holding on to references to these objects (in case that matters for the caller),
        // - and to reduce memory requirements
        foreach ($newStack as $index => $step) {
            if (is_object($step['object'] ?? null)) {
                $newStack[$index]['object'] = spl_object_id($step['object']);
            }
        }

        // edge case:
        // when Clarity methods are called via call_user_func_array(..), the callstack's most recent frame is an extra
        // frame that's missing the "file" and "line" keys
        //
        // this causes clarity not to remember meta-data, because it's associated to a "phantom" frame that's forgotten
        // the moment the callstack is inspected next
        //
        // skipping this frame brings the most recent frame back to the place where call_user_func_array was called
        while (
            (count($newStack))
            && ((!array_key_exists('file', $newStack[0])) || (!array_key_exists('line', $newStack[0])))
        ) {
            array_shift($newStack);
        }

        // shift the frame details by one so they make more sense
        // only bother doing this for the frame that gets recorded with the meta-data
        if (count($newStack) > 1) {

            /** @var mixed[] $lastFrame */
            $lastFrame = $newStack[0];

            /** @var mixed[] $replacementFrame */
            $replacementFrame = $newStack[1];
            $replacementFrame['file'] = $lastFrame['file'] ?? $replacementFrame['file'];
            $replacementFrame['line'] = $lastFrame['line'] ?? $replacementFrame['line'];

            $newStack[0] = $replacementFrame;
        }

        return array_reverse($newStack);
    }





    /**
     * Store the current stack, and purge any stack-content that doesn't sit inside it anymore.
     *
     * @param array<integer, mixed[]> $newStack The new stack to store.
     * @return void
     */
    private function replaceCallStack(array $newStack): void
    {
        $this->pruneOffOldMetaData($newStack);

        $this->callStack = $newStack;
    }

    /**
     * Remove meta-data that should be pruned.
     *
     * @param array<integer, mixed[]> $newStack        The new stack to compare against.
     * @param string[]                $fieldsToCompare The fields from each frame to compare (whole frames are compared
     *                                                 by default).
     * @return void
     */
    private function pruneOffOldMetaData(array $newStack, array $fieldsToCompare = []): void
    {
        $staleFrameIndexes = $this->findPrunableFrames($this->callStack, $newStack, $fieldsToCompare);

        foreach ($staleFrameIndexes as $frameIndex) {
            unset($this->stackMetaData[$frameIndex]);
        }
    }



    /**
     * Compare two callstacks, and work out which frames from the old stack needs pruning.
     *
     * Returns the frames' indexes.
     *
     * @param array<integer, mixed[]> $oldStack        The old stack to compare.
     * @param array<integer, mixed[]> $newStack        The new stack to compare.
     * @param string[]                $fieldsToCompare The fields from each frame to compare (whole frames are compared
     *                                                 by default).
     * @return integer[]
     */
    private function findPrunableFrames(array $oldStack, array $newStack, array $fieldsToCompare = []): array
    {
        $diffPos = count($fieldsToCompare)
            ? $this->findDiffPosCompareFields($oldStack, $newStack, $fieldsToCompare)
            : $this->findDiffPos($oldStack, $newStack);

        return array_slice(array_keys($this->callStack), $diffPos + 1, null, true);
    }

    /**
     * Find the first position of the two arrays where their values are different.
     *
     * @param array<integer, mixed[]> $oldStack The old stack to compare.
     * @param array<integer, mixed[]> $newStack The new stack to compare.
     * @return integer
     */
    private function findDiffPos(array $oldStack, array $newStack): int
    {
        if (!count($oldStack)) {
            return 0;
        }

        $index = 0;
        foreach ($newStack as $index => $newFrame) {
            if ($newFrame !== ($oldStack[$index] ?? null)) {
                break;
            }
        }
        return $index;
    }

    /**
     * Find the first position of the two arrays where their values are different. Compares particular keys from each.
     *
     * @param array<integer, mixed[]> $oldStack        The old stack to compare.
     * @param array<integer, mixed[]> $newStack        The new stack to compare.
     * @param string[]                $fieldsToCompare The fields from each frame to compare.
     * @return integer
     */
    private function findDiffPosCompareFields(array $oldStack, array $newStack, array $fieldsToCompare = []): int
    {
        $index = 0;
        foreach ($newStack as $index => $newFrame) {

            if (!array_key_exists($index, $oldStack)) {
                break;
            }
            $oldFrame = $oldStack[$index];

            foreach ($fieldsToCompare as $field) {
                if (($newFrame[$field] ?? null) !== ($oldFrame[$field] ?? null)) {
                    break 2;
                }
            }
        }
        return $index;
    }





    /**
     * Record some meta-data, at the current point in the stack.
     *
     * @param string  $type      The type of meta-data to save.
     * @param mixed   $value     The value to save.
     * @param mixed[] $frameData The number of frames to go back, to get the intended caller frame.
     * @return void
     */
    private function recordMetaData(string $type, mixed $value, array $frameData): void
    {
        /** @var integer $frameIndex */
        $frameIndex = array_key_last($this->callStack);

        $line = is_int($frameData['line'] ?? null) ? $frameData['line'] : null;

        $index = $this->resolveMetaDataIndexToUse($frameIndex, $type, $line);

        $this->stackMetaData[$frameIndex][$index] = [
            'type' => $type,
            'frame' => $frameData,
            'value' => $value,
        ];
    }

    /**
     * Determine the position in the stackMetaData array to update.
     *
     * If meta-data was defined before on the same line, it will be updated.
     *
     * This allows for the code inside a loop to update its meta-data, instead of continually adding more.
     *
     * @param integer      $frameIndex The index of the stackContent array.
     * @param string       $type       The type of meta-data to save.
     * @param integer|null $line       The line that made the call.
     * @return integer
     */
    private function resolveMetaDataIndexToUse(int $frameIndex, string $type, ?int $line): int
    {
        /** @var mixed[] $metaData */
        foreach ($this->stackMetaData[$frameIndex] ?? [] as $index => $metaData) {

            if ($metaData['type'] !== $type) {
                continue;
            }
            // it *has* to be the same file, as it's the same position in the callstack
//            if ($metaData['frame']['file'] ?? null !== $file) {
//                continue;
//            }
            if (($metaData['frame']['line'] ?? null) !== $line) {
                continue;
            }

            return $index;
        }

        return count($this->stackMetaData[$frameIndex] ?? []);
    }





    /**
     * Remove existing meta-data from the top of the callstack.
     *
     * @param string $type The type of meta-data to remove.
     * @return void
     */
    private function removeMetaDataFromTop(string $type): void
    {
        $lastFrame = max(array_keys($this->callStack));
        if (!array_key_exists($lastFrame, $this->stackMetaData)) {
            return;
        }

        foreach (array_keys($this->stackMetaData[$lastFrame]) as $index) {

            if ($this->stackMetaData[$lastFrame][$index]['type'] == $type) {
                unset($this->stackMetaData[$lastFrame][$index]);
            }
        }

        // re-index so the indexes are sequential
        // this is so that resolveMetaDataIndexToUse above doesn't have trouble when determining the next index to use
        $this->stackMetaData[$lastFrame] = array_values($this->stackMetaData[$lastFrame]);
    }
}
