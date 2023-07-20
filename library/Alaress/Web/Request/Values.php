<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Request;

use ArrayObject;

/**
 * Port of `\Aura\Web\Request\Values`: ported as it provides some convenience
 * methods for fetching data of a particular type.
 *
 * Example:
 * ```php
 * // From GET or POST data:
 * $requestQuery = new Values($_GET);
 * $requestPost  = new Values($_POST);
 *
 * // From a PSR-7 request:
 * parse_str($request->getUri()->getQuery(), $queryArray);
 * $requestQuery = new Values($queryArray);
 * $requestPost  = new Values($request->getParsedBody());
 * ```
 *
 * Differences from `\Aura\Web\Request\Values`:
 * - typed `get` methods have type definitions
 * - typed `get` methods return `null` if they can't be cast to the requested
 *   type
 * - does not support multi-dimensional traversal, by providing a key which
 *   contains a `.` character
 */
class Values extends ArrayObject
{
    /**
     * Truthy values, as defined in original implementation.
     */
    private const TRUTHY_VALUES = ['1', 'on', 'true', 't', 'yes', 'y'];

    /**
     * Falsy values, as defined in original implementation.
     */
    private const FALSY_VALUES = ['', '0', 'off', 'false', 'f', 'no', 'n'];

    /**
     * Retrieves a value from the array.
     *
     * @param string     $key
     * @param mixed|null $alt
     * @return mixed
     */
    public function get(string $key, mixed $alt = null): mixed
    {
        return $this[$key] ?? $alt;
    }

    /**
     * Retrieves a value from the array as an int, if it's either an int or it
     * is castable to one.
     *
     * @param string   $key
     * @param int|null $alt
     * @return int|null The value from the array as an int, or null if it isn't
     *                  castable or doesn't exist.
     */
    public function getInt(string $key, ?int $alt = null): ?int
    {
        $val = $this->get($key, $alt);
        if (!is_numeric($val)) {
            return null;
        }
        return (int)$val;
    }

    /**
     * Retrieves a value from the array as a float, if it's either a float or it
     * is castable to one.
     *
     * @param string     $key
     * @param float|null $alt
     * @return float|null The value from the array as a float, or null if it
     *                    isn't castable or doesn't exist.
     */
    public function getFloat(string $key, ?float $alt = null): ?float
    {
        $val = $this->get($key, $alt);
        if (!is_numeric($val)) {
            return null;
        }
        return (float)$val;
    }

    /**
     * Retrieves a value from the array as a bool, if it's either:
     * - a bool
     * - a numeric value (cast to bool)
     * - a truthy or falsy value
     *
     * @param string    $key
     * @param bool|null $alt
     * @return bool|null The value from the array as a bool, or null if it isn't
     *                   sensibly castable to a bool.
     */
    public function getBool(string $key, ?bool $alt = null): ?bool
    {
        $val = $this->get($key, $alt);
        if (is_bool($val)) {
            return $val;
        }
        if (is_numeric($val)) {
            return (bool)$val;
        }
        if (in_array($val, self::FALSY_VALUES, true)) {
            return false;
        }
        if (in_array($val, self::TRUTHY_VALUES, true)) {
            return true;
        }
        return null;
    }
}
