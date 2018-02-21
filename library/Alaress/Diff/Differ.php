<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Diff;

/**
 * Recursive differ for arrays.
 */
class Differ
{
    /**
     * Recursively diffs `$a` and `$b`.
     *
     * @param array $a
     * @param array $b
     * @return array A summary of the differences between `$a` and `$b`.
     *               This will have the same key structure as its inputs, and
     *               the value will be [`$a.value`, `$b.value`].
     * @throws \InvalidArgumentException if somewhere in the process an array
     *                                   gets diffed with a non-null scalar
     */
    public function diff(array $a, array $b): array
    {
        return $this->diffInternal($a, $b, '');
    }

    /**
     * Internal diff generation: it tracks the path that is being diffed, so
     * that if an `\InvalidArgumentException` is raised the user is informed of
     * where in the array the diff failed (see the throws annotation).
     *
     * @param array  $a
     * @param array  $b
     * @param string $path
     * @return array
     * @throws \InvalidArgumentException if somewhere in the process an array
     *                                   gets diffed with a non-null scalar
     */
    private function diffInternal(array $a, array $b, string $path): array
    {
        $diff = [];

        // Ensure all the same keys exist in $a and $b
        foreach (array_diff_key($a, $b) as $key => $_) {
            $b[$key] = \is_array($a[$key]) ? [] : null;
        }
        foreach (array_diff_key($b, $a) as $key => $_) {
            $a[$key] = \is_array($b[$key]) ? [] : null;
        }

        // Check each value
        foreach ($a as $key => $aValue) {
            $bValue   = $b[$key];
            $thisPath = $path . $key;

            // Both $aValue and $bValue must be arrays, or neither may be.
            // If one is and another isn't: that's an error
            $aIsArray = \is_array($aValue);
            $bIsArray = \is_array($bValue);
            if ($aIsArray xor $bIsArray) {
                throw new \InvalidArgumentException(
                    sprintf('Cannot diff array with a non-array in key "%s"', $thisPath)
                );
            }

            // Is this scalar? Diff it now, otherwise recurse
            if ($aIsArray && $bIsArray) {
                $recursiveDiff = $this->diffInternal($aValue, $bValue, $thisPath . '.');
                if (!empty($recursiveDiff)) {
                    $diff[$key] = $recursiveDiff;
                }
            } elseif ($aValue !== $bValue) {
                $diff[$key] = [$aValue, $bValue];
            }
        }
        return $diff;
    }
}
