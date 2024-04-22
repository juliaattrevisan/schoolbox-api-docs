<?php

/**
 * Class for validating that an array contains expected keys, and that the value for each key has the expected type.
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress;

use BadFunctionCallException;
use Exception;
use InvalidArgumentException;

class ArrayValidator
{
    /**
     * @throws BadFunctionCallException
     */
    public function __construct()
    {
        throw new BadFunctionCallException('This class only contains static functions, so no need to instantiate.');
    }

    /**
     * @param array $array
     * @param array $typeByRequiredKey
     * @param array $typeByOptionalKey
     * @param bool  $ignoreExtraKeys
     * @return bool
     */
    public static function checkArrayReturnBool(
        array $array,
        array $typeByRequiredKey,
        array $typeByOptionalKey = [],
        bool $ignoreExtraKeys = false
    ): bool {
        try {
            self::checkArray($array, $typeByRequiredKey, $typeByOptionalKey, $ignoreExtraKeys);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * @param array $array
     * @param array $typeByRequiredKey
     * @param array $typeByOptionalKey
     * @param bool  $ignoreExtraKeys
     * @return void
     */
    public static function checkArray(
        array $array,
        array $typeByRequiredKey,
        array $typeByOptionalKey = [],
        bool $ignoreExtraKeys = false
    ): void {
        $nKeys         = count($array);
        $nKeysRequired = count($typeByRequiredKey);
        $nKeysOptional = count($typeByOptionalKey);
        $nKeysMax      = $nKeysRequired + $nKeysOptional;

        if ($nKeys < $nKeysRequired) {
            throw new InvalidArgumentException(
                "Too few keys in array ($nKeys keys).  Expected number in range [$nKeysRequired, $nKeysMax]." .
                '  Missing keys: {' . implode(', ', array_diff(array_keys($typeByRequiredKey), array_keys($array))) . '}'
            );
        } elseif ($nKeys > $nKeysMax && !$ignoreExtraKeys) {
            throw new InvalidArgumentException(
                "Too many keys in array ($nKeys keys).  Expected number in range [$nKeysRequired, $nKeysMax]." .
                '  Extra keys: {' .
                implode(', ', array_diff(array_keys($array), array_merge(array_keys($typeByRequiredKey), array_keys($typeByOptionalKey)))) . '}'
            );
        }

        // Check required keys and types.
        foreach ($typeByRequiredKey as $key => $type) {
            if (!array_key_exists($key, $array)) {
                throw new InvalidArgumentException("Required key '$key' does not exist in array.");
            }

            // TODO: If we ever want to implement recursive checking, maybe do it like this:
            // if (is_array($type)) {
            //     self::checkType($array[$key], 'array');
            //     self::checkArray($type, ['expectedKeys' => 'array', 'optionalKeys' => 'array', 'ignoreExtraKeys' => 'bool']);
            //     self::checkArray($array[$key], $type['expectedKeys'], $type['optionalKeys'], $type['ignoreExtraKeys']);
            // }

            try {
                self::checkType($array[$key], $type);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException("Type check failed for required key '$key'.\n" . $e->getMessage());
            }
        }

        // Check optional keys and types.
        $arrayExtra = array_diff_key($array, $typeByRequiredKey);
        foreach (array_keys($arrayExtra) as $key) {
            if (!array_key_exists($key, $typeByOptionalKey)) {
                if ($ignoreExtraKeys) {
                    continue;
                }

                throw new InvalidArgumentException("Unexpected key '$key' found.");
            }

            try {
                self::checkType($array[$key], $typeByOptionalKey[$key]);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException("Type check failed for optional key '$key'.\n" . $e->getMessage());
            }
        }
    }

    /**
     * @param mixed  $v
     * @param string $type
     * @return void
     */
    public static function checkType(mixed $v, string $type): void
    {
        switch ($type) {
            // Basic types.
            case 'array':
                $b = is_array($v);
                break;
            case 'bool': // Fall through.
            case 'boolean':
                $b = is_bool($v);
                break;
            case 'float':
                $b = is_float($v);
                break;
            case 'int':
                $b = is_int($v);
                break;
            case 'null':
                $b = is_null($v);
                break;
            case 'numeric':
                $b = is_numeric($v);
                break;
            case 'object':
                $b = is_object($v);
                break;
            case 'resource':
                $b = is_resource($v);
                break;
            case 'scalar':
                $b = is_scalar($v);
                break;
            case 'string':
                $b = is_string($v);
                break;
            case 'floatString':
                $b = filter_var($v, FILTER_VALIDATE_FLOAT) !== false;
                break;

            // More complicated types can be added here as needed.
            // E.g. 'nullOrArray', 'nullOrString', 'nonEmptyString', 'nonEmptyArray', 'positiveInt', 'nonNegativeInt', 'dateStringY-m-d', etc.

            default:
                if (gettype($v) === 'object') {
                    $b = (get_class($v) === $type);
                } else {
                    throw new InvalidArgumentException("Unknown validation type '$type'.");
                }
        }

        if (!$b) {
            throw new InvalidArgumentException(
                "Variable type check failed.  Expected '$type', received " . (
                    (gettype($v) == 'object')
                    ? 'object of class \'' . get_class($v) . '\'.'
                    : 'variable of type \'' . gettype($v) . '\'' . (gettype($v) == 'string' ? " ('$v')" : '') . ".  (value: $v)."
                )
            );
        }
    }
}
