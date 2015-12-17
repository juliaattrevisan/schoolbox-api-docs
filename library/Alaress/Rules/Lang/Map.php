<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules\Lang;


class Map implements Construct
{
    /** @var string */
    protected $field;

    /** @var array */
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
                $output[] = $this->map[$input];
            }
        }
        return $output;
    }

} 
