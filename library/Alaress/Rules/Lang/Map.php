<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Rules\Lang;

use Alaress\Rules\Matcher\Matcher;

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
     *        This pattern may contain wildcards (@see Matcher for format)
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
                    $isMatch = (new Matcher($mapKey))->match($input ?? '');
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

        // Did nothing match?
        // Return a special value if so: that is a prompt to leave the existing
        // value as is.
        if (empty($output)) {
            throw new NoMatchingMapException();
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
