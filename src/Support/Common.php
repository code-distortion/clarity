<?php

namespace CodeDistortion\Clarity\Support;

/**
 * Generic methods, shared by this package.
 */
class Common
{
    /**
     * Loop through the arguments, and normalise them into a single array, merged with previously existing values.
     *
     * @param mixed[] $previous The values that were set previously, to be merged into.
     * @param mixed[] $args     The arguments that were passed to the method that called this one.
     * @return mixed[]
     */
    public static function normaliseArgs(array $previous, array $args): array
    {
        foreach ($args as $arg) {
            $arg = is_array($arg)
                ? $arg
                : [$arg];
            $previous = array_merge($previous, $arg);
        }
        return array_unique(
            array_filter($previous),
            SORT_REGULAR
        );
    }

    /**
     * Resolve the path of the file, relative to the project root.
     *
     * @param string $file           The file that made the call.
     * @param string $projectRootDir The root directory of the project.
     * @return string
     */
    public static function resolveProjectFile(string $file, string $projectRootDir): string
    {
        if (!mb_strlen($projectRootDir)) {
            return $file;
        }

        $path = mb_substr($file, 0, mb_strlen($projectRootDir)) == $projectRootDir
            ? mb_substr($file, mb_strlen($projectRootDir))
            : $file;

        return mb_strlen($path)
            ? $path
            : '';
    }

    /**
     * Work out if this is an application (i.e. non-vendor) frame or not.
     *
     * @param string $projectFile    The path of the file, relative to the project root.
     * @param string $projectRootDir The root directory of the project.
     * @return boolean
     */
    public static function isApplicationFrame(string $projectFile, string $projectRootDir): bool
    {
        // vendor files cannot be resolved when there's no project root
        if (!mb_strlen($projectRootDir)) {
            return true;
        }

        $vendorDir = DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR;
        return mb_substr($projectFile, 0, mb_strlen($vendorDir)) != $vendorDir;
    }
}
