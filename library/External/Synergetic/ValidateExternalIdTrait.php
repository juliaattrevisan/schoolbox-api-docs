<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/**
 * Trait for validating whether an external ID is valid.
 */
trait External_Synergetic_ValidateExternalIdTrait
{
    private static $mssqlIntMax = 2147483647;

    /**
     * Validates that a Synergetic user ID is an integer.
     *
     * Queries will have unpredictable results or will fail outright if called
     * with an ID that isn't a number.
     *
     * @param  mixed $externalUserId
     * @return bool
     */
    protected function isExternalIdValid($externalUserId)
    {
        // Empty? Reject
        // Any non-digit characters? Reject
        // Out of mssql int range? Reject
        return !empty($externalUserId) && !preg_match('/\\D/', $externalUserId) && ($externalUserId <= self::$mssqlIntMax);
    }
}
