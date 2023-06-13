<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Diff;

/**
 * Recursive differ for arrays.
 */
class Differ
{
    /**
     * Constructor
     *
     * @param int|string $aKey The key for the value of `$a` in the diff:
     *                         defaults to 0, so that it is the first value in
     *                         a list.
     * @param int|string $bKey The key for the value of `$b` in the diff.
     *                         defaults to 1, so that it is the second value in
     *                         a list.
     */
    public function __construct(
        private int|string $aKey = 0,
        private int|string $bKey = 1,
    ) {
    }

    /**
     * Recursively diffs `$a` and `$b`.
     *
     * @param array $a
     * @param array $b
     * @return array A summary of the differences between `$a` and `$b`.
     *               This will have the same key structure as its inputs, and
     *               the value will be:
     *               [$this->aKey => `$a.value`, $this->bKey => `$b.value`].
     */
    public function diff(array $a, array $b): array
    {
        return $this->diffInternal($a, $b);
    }

    /**
     * Internal diff generation
     *
     * @param array  $a
     * @param array  $b
     * @return array
     */
    private function diffInternal(array $a, array $b): array
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

            // Both $aValue and $bValue must be arrays, or neither may be.
            // If one is and another isn't the two arrays will be returned as the different values
            $aIsArray = \is_array($aValue);
            $bIsArray = \is_array($bValue);

            // Is this scalar? Diff it now, otherwise recurse
            if ($aIsArray && $bIsArray) {
                $recursiveDiff = $this->diffInternal($aValue, $bValue);
                if (!empty($recursiveDiff)) {
                    $diff[$key] = $recursiveDiff;
                }
            } elseif ($aValue !== $bValue) {
                $diff[$key] = [$this->aKey => $aValue, $this->bKey => $bValue];
            }
        }
        return $diff;
    }
}
