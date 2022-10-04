<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Rules\Lang;

use PHLAK\Splat\Pattern;

/**
 * A language construct for mapping SIS output (possibly modified with tokens)
 * to one or more final values.
 */
class Map implements Construct
{
    /**
     * The field within the object which is to be modified by this mapping.
     */
    private string $field;

    /**
     * The mapping itself.
     *
     * key:   the value which will be compared to each `$inputValue` passed to
     *        `apply()`.
     *        This pattern may contain wildcards
     *        (@see https://github.com/PHLAK/Splat):
     *        * "*" matches any character sequence
     *        * "?" matches any single character
     *        * "[abc]" matches any character in the set
     *        * "[a-c]" matches any character in the range
     *        * "[^abc]" matches any character not in the set
     *        * "[^a-c]" matches any character not in the range
     *        * "{foo,bar,baz}" matches if any of the comma-separated
     *          sub-patterns also match
     *        * "(=foo)" matches if the string also contains the pattern
     *        * "(!foo)" matches if the string doesn't contain the pattern
     *        * "\" escapes any wildcard character
     * value: the value(s) which will be set in the object. This may be a
     *        multidimensional array: it will be converted into a flat array.
     *
     * @var array<string, string|array>
     */
    private array $map;

    /**
     * @param string $field
     * @param array  $map
     */
    public function __construct(string $field, array $map)
    {
        $this->field = $field;
        $this->map   = $map;
    }


    /**
     * @return string
     */
    public function getSummary()
    {
        $str = " MAPS '$this->field' AS [\n  ";

        $str .= implode("\n  ", array_map(function ($key, $value) {

            if (is_array($value)) {
                $value = '[' . implode(', ', $value) . ']';
            }

            return "'$key' => $value";
        }, array_keys($this->map), $this->map));

        $str .= "\n]";

        return $str;
    }


    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }


    /**
     * @param array $inputValue will be an array of strings
     * @return mixed
     */
    public function apply($inputValue)
    {
        $output = [];
        foreach ($inputValue as $input) {
            foreach ($this->map as $mapKey => $mapVal) {
                // Does the pattern in $mapKey match the actual pattern?
                if ($mapKey === $input) {
                    $isMatch = true;
                } else {
                    // Case-insensitive match vs. pattern
                    $pattern = new Pattern($mapKey);
                    $regexp  = $pattern->toRegex() . 'i';
                    $isMatch = preg_match($regexp, $input);
                }

                if ($isMatch) {
                    if (is_array($mapVal)) {
                        $output = array_merge($output, $this->flattenArray($mapVal));
                    } else {
                        $output[] = $mapVal;
                    }
                }
            }
        }
        return array_values(array_unique($output));
    }

    /**
     * @param array $array  A potentially multi-dimensional array
     * @return array        A flat single-dimension array containing all values
     */
    protected function flattenArray(array $array)
    {
        $values = [];
        foreach ($array as $value) {
            if (is_array($value)) {
                $values = array_merge($values, $this->flattenArray($value));
            } else {
                $values[] = $value;
            }
        }
        return $values;
    }
}
