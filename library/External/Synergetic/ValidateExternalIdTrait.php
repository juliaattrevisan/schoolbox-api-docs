<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * Trait for validating whether an external ID is valid.
 */
trait External_Synergetic_ValidateExternalIdTrait
{
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
        return !empty($externalUserId) && !preg_match('/\\D/', $externalUserId);
    }
}
