<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\DataStructures;

use function array_push;
use function count;

/**
 * Generates permutations of an array.
 */
class Permutations
{

    /**
     * Generates and returns all permutations of an array.
     *
     * Does not deduplicate results if an item is present multiple times, eg:
     * [1,1] => [1,1], [1,1]
     *
     * @param array $items
     * @return array
     */
    public function generate(array $items): array
    {
        return $this->generateRecursive(count($items), $items);
    }


    /**
     * Generates and returns all permutations of an array.
     *
     * Does deduplicate results if an item is present multiple times, eg:
     * [1,1] => [1,1]
     *
     * @param array         $items
     * @param callable|null $equals Method to determine if items are equal, defaults to ===
     * @return array
     */
    public function generateDistinct(array $items, callable $equals = null): array
    {
        $permutations = $this->generateRecursive(count($items), $items);
        $equals       = $equals ?? static function ($a, $b): bool {
                return $a === $b;
            };

        foreach ($permutations as $i => $permutation) {
            $limit = count($permutations);
            for ($j = $i + 1; $j < $limit; $j++) {
                if ($this->arrayEquals($permutation, $permutations[$j], $equals)) {
                    unset($permutations[$j]);
                    break;
                }
            }
        }
        return $permutations;
    }


    /**
     * Returns whether two arrays are equal
     *
     * @param array    $a
     * @param array    $b
     * @param callable $equals
     * @return bool
     */
    private function arrayEquals(array $a, array $b, callable $equals): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }
        foreach ($a as $key => $valA) {
            if (!$equals($valA, $b[$key])) {
                return false;
            }
        }
        return true;
    }


    /**
     * Returns all possible permutations of an array
     *
     * @see https://en.wikipedia.org/wiki/Heap%27s_algorithm
     *
     * @param int   $k
     * @param array $array
     * @return array
     */
    private function generateRecursive(int $k, array &$array): array
    {
        $permutations = [];
        if ($k === 1) {
            $permutations[] = $array;
        } else {
            $permutations = $this->generateRecursive($k - 1, $array);

            for ($i = 0; $i < ($k - 1); $i++) {

                if ($k % 2 === 0) {
                    // If size is even, swap ith and last element
                    $temp          = $array[$i];
                    $array[$i]     = $array[$k - 1];
                    $array[$k - 1] = $temp;
                } else {
                    // if size is odd, swap first and last element
                    $temp          = $array[0];
                    $array[0]      = $array[$k - 1];
                    $array[$k - 1] = $temp;
                }
                array_push($permutations, ...$this->generateRecursive($k - 1, $array));
            }

        }
        return $permutations;
    }
}
