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
use Traversable;

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
        if (is_array($value)) {
            // attempt to recover
            $filtered = array_filter($value);

            if (count($filtered) > 1) {
                throw new DomainException("Value cannot be coerced to string");
            }

            if (count($filtered) == 0) {
                return null;
            }

            return $filtered[0];
        }

        if (is_object($value) && !method_exists($value, '__toString')) {
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

        // replace array[notation] with dot.notation
        $path = preg_replace('`\[(\d+)\]`', '.$1', $path);

        $parts  = explode('.', $path);

        try {
            foreach ($parts as $get) {

                if ($value instanceof ArrayAccess) {

                    if ($value->offsetExists($get)) {
                        // simple. just get it.
                        $string .= "[$get]";
                        $value = $value->offsetGet($get);

                    } else {
                        // so that offset doesn't exist...
                        // BUT! ...maybe it exists in the subordinate objects (and we're getting a collection return)
                        if (!$value instanceof Traversable) {
                            return null;
                        }

                        $values = [];
                        $append = '';

                        foreach ($value as $k => $v) {

                            try {
                                if (is_object($v)) {
                                    $append =  '->get'.ucfirst($get).'()[]';
                                    $values[] = $this->readObjectProperty($v, $get);
                                } elseif (is_array($v)) {
                                    $append = "[$get][]";
                                    $values[] = $this->readArrayProperty($v, $get);
                                }
                            } catch (DomainException $e) {
                                // do nothing - ignore it and keep going
                            }

                        }

                        if (count($values) == 0) {
                            // nothing in the traversable matched, bug out
                            return null;
                        }

                        // this array is what we'll operate on from here
                        $string .= $append;
                        $value = $values;

                    }

                } elseif (is_object($value)) {
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
        // test for getter access
        $method = array($object, "get$get"); // methods aren't case sensitive......

        if (is_callable($method)) {
            return call_user_func($method);
        }

        // test for direct access
        if (property_exists($object, $get)) {
            return $object->$get;
        }

        throw new DomainException(sprintf("Property %s.%s is not readable", get_class($object), $get));
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