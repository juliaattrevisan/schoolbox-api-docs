<?php
/**
 * Offline stub for External_UserDetentionInterface
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Offline_UserDetention implements External_UserDetentionInterface {
    /**
     * Retrieves a user's awards.
     *
     * @param mixed    $externalId The student's external ID.
     * @param DateTime $fromDate   The date to fetch from, or null to get all
     *                             in the past.
     * @param DateTime $toDate     The date to fetch until, or null to get all
     *                             in the future.
     *
     * @return array
     */
    public function getAwards(
        $externalId,
        DateTime $fromDate = null,
        DateTime $toDate = null
    ) {
        return array();
    }

    /**
     * Retrieves a user's detentions.
     *
     * @param mixed    $externalId The student's external ID.
     * @param DateTime $fromDate   The date to fetch from, or null to get all
     *                             in the past.
     * @param DateTime $toDate     The date to fetch until, or null to get all
     *                             in the future.
     *
     * @return array
     */
    public function getDetentions(
        $externalId,
        DateTime $fromDate = null,
        DateTime $toDate = null
    ) {
        return array();
    }

    /**
     * Retrieves a user's absences that do not have an absence code.
     *
     * @param mixed    $externalId The student's external ID.
     * @param DateTime $fromDate   The date to fetch from, or null to get all
     *                             in the past.
     * @param DateTime $toDate     The date to fetch until, or null to get all
     *                             in the future.
     *
     * @return array
     */
    public function getAbsences(
        $externalId,
        DateTime $fromDate = null,
        DateTime $toDate = null
    ) {
        return array();
    }

}
