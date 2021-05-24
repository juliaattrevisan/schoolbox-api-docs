<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Weather;

/**
 * Class Location
 *
 * Determine the location for which to obtain weather data
 *
 * Falls through multiple configurable values preferentially:
 *     1 user-specified location     $specifiedLocation
 *     2 system-specified location   $defaultLocation
 *     3 hardcoded default           DEFAULT_LOCATION
 *
 * @package Schoolbox\Weather
 */
class Location
{

    /**
     * Default location for which to obtain weather data
     */
    private const DEFAULT_LOCATION = 'Melbourne, Australia';


    /**
     * Location for which to get the weather
     *
     * @param string $specifiedLocation
     * @param string $defaultLocation
     * @return string
     */
    public function detect(string $specifiedLocation, string $defaultLocation): string
    {
        /* First check the component configuration - if there's a location there, use that
         */
        $location = trim($specifiedLocation);

        if ('' !== $location) {
            return $location;
        }

        /* Then check the system config for a default location - if there's a location there, use that
         */
        $location = trim($defaultLocation);

        if ('' !== $location) {
            return $location;
        }

        /* Otherwise if that doesn't exist for some reason, default to Melbourne Australia
         */
        return self::DEFAULT_LOCATION;
    }
}
