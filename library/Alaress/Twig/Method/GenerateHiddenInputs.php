<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * @param iterable $array
     * @param array    $prefixes
     * @return string
     */
    public function __invoke(iterable $array, array $prefixes = []): string
    {
        $result = [];
        foreach ($array as $name => $value) {
            $nameParts   = $prefixes;
            $nameParts[] = $name;
            if (is_iterable($value)) {
                $result[] = $this($value, $nameParts);
            } else {
                $result[] = sprintf(
                    '<input type="hidden" name="%s" value="%s" />',
                    htmlspecialchars($this->generateInputName($nameParts), ENT_QUOTES),
                    htmlspecialchars($value, ENT_QUOTES)
                );
            }
        }
        return implode("\n", $result);
    }


    /**
     * Generate the name for a form input.
     *
     * @example `generateInputName(['user']) => 'user')
     * @example `generateInputName(['user', 'firstname']) => 'user[firstname]')
     * @example `generateInputName(['user', 'name', 'first']) => 'user[name][first]')
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
