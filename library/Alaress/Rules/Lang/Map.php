<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Rules\Lang;

class Map implements Construct
{
    /** @var string */
    protected $field;

    /**
     * Each element may be a scalar or itself an array
     *
     * @var array
     */
    protected $map;


    /**
     * @param string $field
     * @param array  $map
     */
    public function __construct($field, array $map)
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
            if (array_key_exists($input, $this->map)) {
                $mapVal = $this->map[$input];
                if (is_array($mapVal)) {
                    $output = array_merge($output, $this->flattenArray($mapVal));
                } else {
                    $output[] = $mapVal;
                }
            }
        }
        return array_unique($output);
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
