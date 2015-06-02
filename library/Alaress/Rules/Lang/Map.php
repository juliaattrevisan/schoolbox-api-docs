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
        $str = " MAPS $this->field AS [\n  ";

        $str .= implode("\n  ", array_map(function ($key, $value) {

            return "$key => ".$this->getObjectOutput($value);

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
     * @param string $inputValue
     * @return mixed
     */
    public function apply($inputValue)
    {
        if (array_key_exists($inputValue, $this->map)) {
            return $this->map[$inputValue];
        }

        return null;
    }


    /**
     * @param mixed $obj
     * @return string
     */
    protected function getObjectOutput($obj)
    {
        if (is_object($obj) && !$obj instanceof \Traversable) {
            if (is_callable([ $obj, '__toString' ])) {
                // just use toString if available
                $obj = "$obj";
            } elseif (is_callable([ $obj, 'getId' ])) {
                // not available? ok, try for Name and Id

                $id   = $obj->getId();
                $name = is_callable([ $obj, 'getName' ]) ? $obj->getName() : get_class($obj);

                $obj = "$name ($id)";
            } else {
                $obj = get_class($obj);
            }

            // give a visual indication that this is an object
            $obj = "<cite>$obj</cite>";
        }

        return $obj;
    }

} 