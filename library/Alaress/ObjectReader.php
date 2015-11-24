<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress;

use Alaress_Date_Format;
use ArrayAccess;
use DomainException;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
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
     * @throws DomainException
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
     * Accepts any input, and returns the list of path strings that could be validly accepted by self::read()
     *
     * @param mixed $input
     * @return array
     */
    public function paths($input)
    {
        // this probably seems trite, but it protects the prefix argument from abuse
        return array_keys($this->recursePaths($input));
    }


    /**
     * @param mixed  $input
     * @param string $prefix
     * @param array  $stack
     * @return array
     */
    protected function recursePaths($input, $prefix = '', $stack = [])
    {
        $paths = [];

        if (is_array($input) || $input instanceof Traversable) {

            foreach ($input as $key => $sample) {
                $paths = $this->flattenPaths($paths, $prefix, $key, $sample, $input, $stack);
            }

        } elseif (is_object($input)) {

            $ro = new ReflectionObject($input);

            foreach ($ro->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                $key = $prop->getName();
                $sample = $prop->getValue($input);

                $paths = $this->flattenPaths($paths, $prefix, $key, $sample, $input, $stack);
            }

            foreach ($ro->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();
                if (strpos($name, 'get') !== 0 || strlen($name) < 4) {
                    continue;
                }

                $key = lcfirst(substr($name, 3));
                $sample = $method->invoke($input);

                $paths = $this->flattenPaths($paths, $prefix, $key, $sample, $input, $stack);
            }

        } else {
            // we've reached a leaf, append it
            $paths[$prefix] = true;
        }

        return $paths;
    }


    /**
     * @param array  $paths
     * @param string $prefix
     * @param string $key
     * @param mixed  $sample
     * @param mixed  $input
     * @param array  $stack
     * @return array
     */
    protected function flattenPaths($paths, $prefix, $key, $sample, $input, $stack)
    {
        // bail out if this iteration is not to continue
        if ($this->isPathBlacklisted($sample) || $this->pathRecursionDetected($stack, $input)) {
            return $paths;
        }

        // input passed, so push it onto the recursion-check stack
        $stack[] = $input;

        // treat integers special-like (since properties and methods cannot start with a number, these must be array/collection keys)
        if (is_int($key)) {
            $newPrefix = $prefix . '[]';
        } else {
            $newPrefix = $prefix ? "$prefix.$key" : $key;
        }

        $result = $this->recursePaths($sample, $newPrefix, $stack);

        return array_merge($paths, $result);
    }


    /**
     * @param mixed $input
     * @return bool
     */
    protected function isPathBlacklisted($input)
    {
        if ($input instanceof Alaress_Date_Format) {
            return true;
        }

        return false;
    }


    /**
     * @param array $stack
     * @param object $input
     * @return bool
     */
    protected function pathRecursionDetected($stack, $input)
    {
        return in_array($input, $stack);
    }

    /**
     * @param object|array $input
     * @param string $path
     * @param bool $requireStringCoercible
     * @throws DomainException
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