<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Twig\Method;

/**
 * Generator for hidden form inputs from array values.
 */
class GenerateHiddenInputs
{
    /**
     * Generates a set of hidden `<input>` elements from `$array`, such that
     * when submitted via a form will recreate `$array` in `$_GET` or `$_POST`.
     *
     * This is called recursively for nested arrays.
     *
     * @param iterable $array     A set of `$_GET` or `$_POST` values.
     * @param array    $nameParts When called with nested arrays, this is the
     *                            set of keys that has lead from the original
     *                            array to here.
     * @return string
     */
    public function __invoke(iterable $array, array $nameParts = []): string
    {
        $result = [];
        foreach ($array as $name => $value) {
            $thisNameParts   = $nameParts;
            $thisNameParts[] = $name;
            if (is_iterable($value)) {
                $result[] = $this($value, $thisNameParts);
            } else {
                $result[] = sprintf(
                    '<input type="hidden" name="%s" value="%s" />',
                    htmlspecialchars($this->generateInputName($thisNameParts), ENT_QUOTES),
                    htmlspecialchars($value, ENT_QUOTES)
                );
            }
        }
        return implode("\n", $result);
    }


    /**
     * Generate the name for a form input.
     *
     * @example `generateInputName(['user']) => 'user')`
     * @example `generateInputName(['user', 'firstname']) => 'user[firstname]')`
     * @example `generateInputName(['user', 'name', 'first']) => 'user[name][first]')`
     *
     * @param iterable|string[] $nameParts The parts of the name.
     * @return string
     */
    private function generateInputName(iterable $nameParts): string
    {
        $name  = [];
        $first = true;
        foreach ($nameParts as $namePart) {
            if (!$first) {
                $namePart = '[' . $namePart . ']';
            }
            $first  = false;
            $name[] = $namePart;
        }
        return implode('', $name);
    }
}
