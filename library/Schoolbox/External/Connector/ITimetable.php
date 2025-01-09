<?php

/**
 * Interface for external timetable
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector;

use Schoolbox\Collection;

interface ITimetable
{
    /**
     * Retrieves the current semester from the SIS.
     *
     * This optionally accepts the ID of an external SIS user, and where supported by the SIS,
     * if supplied this will return the semester that this user currently has set as their
     * default. If the SIS does not support this, then it will return the current semester
     * based on the current calendar date.
     *
     * The return value is either a string/int based on the format the external SIS uses
     *
     * @param null|int|string $externalId Optional external user ID
     * @return string|int|null
     */
    function getCurrentSemester($externalId = null);

    /**
     * Retrieves the current year from the SIS.
     *
     * This optionally accepts the ID of an external SIS user, and where supported by the SIS,
     * if supplied this will return the year that this user currently has set as their
     * default. If the SIS does not support this, then it will return the current year
     * based on the current calendar date.
     *
     * @param null|int|string $externalId Optional external user ID
     * @return int|null
     */
    function getCurrentYear($externalId = null);
}
