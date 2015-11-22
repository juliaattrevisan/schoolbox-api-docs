<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress;

use ArrayAccess;
use DomainException;

/**
 * Class ObjectReader
 *
 * This is a generic utility class to, as cleverly as possible, read hierarchical data out of an object
 *
 * @package Alaress
 */
class ObjectReader
{

    /**
     * @param mixed $value
     * @return string
     */
    public function coerceToString($value)
    {
        if (is_array($value) || (is_object($value) && !method_exists($value, '__toString'))) {
            throw new DomainException("Value cannot be coerced to string");
        }

        return (string)$value;
    }

    /**
     * @param object|array  $input
     * @param string        $path
     * @param bool          $requireStringCoercible
     * @return mixed
     */
    public function read($input, $path, $requireStringCoercible = false)
    {
        $value  = $input;
        $string = is_object($input) ? get_class($input) : gettype($input);
        $parts  = explode('.', $path);

        try {
            foreach ($parts as $get) {

                if ($value instanceof ArrayAccess) {

                    if (!$value->offsetExists($get)) {
                        // it's empty! bug out early and just return null
                        return null;
                    }

                    $string .= "[$get]";
                    $value = $value->offsetGet($get);
                }
                elseif (is_object($value)) {
                    $string .= '->get'.ucfirst($get).'()';
                    $value = $this->readObjectProperty($value, $get);

                } elseif (is_array($value)) {
                    $string .= "[$get]";
                    $value = $this->readArrayProperty($value, $get);
                } else {
                    throw new DomainException("Reading '$get' from $string, but property '$value' is not readable (path was $path)");
                }
            }
        } catch (DomainException $e) {
            throw new DomainException("Failed to retrieve $string, does not exist", 0, $e);
        }

        if ($requireStringCoercible) {
            $value = $this->coerceToString($value);
        }

        return $value;
    }


    /**
     * @param object $object
     * @param string $get
     * @throws DomainException
     * @return mixed
     */
    protected function readObjectProperty($object, $get)
    {
        $method = array($object, "get$get"); // methods aren't case sensitive......

        if (is_callable($method)) {
            return call_user_func($method);
        }

        throw new DomainException(sprintf("Method %s::%s() is not callable", get_class($object), $method[1]));
    }


    /**
     * @param array  $array
     * @param string $get
     * @throws DomainException
     * @return mixed
     */
    protected function readArrayProperty($array, $get)
    {
        if (array_key_exists($get, $array)) {
            return $array[$get];
        }

        throw new DomainException("Key '$get' not found on array");
    }

}