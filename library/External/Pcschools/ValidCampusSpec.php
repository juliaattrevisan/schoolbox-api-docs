<?php

declare(strict_types=1);

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Campus;

/**
 * Specification for whether a campus is a valid campus for use as a PCSchool
 * campus.
 */
class External_Pcschools_ValidCampusSpec
{
    /**
     * Does this campus satisfy the specification for a valid PCSchool campus?
     *
     * Its campus code must be a valid numeric value.
     *
     * @param Campus $campus
     * @return bool
     */
    public function isSatisfiedBy(Campus $campus): bool
    {
        return is_numeric(trim($campus->getExtTimetableCode()));
    }
}
