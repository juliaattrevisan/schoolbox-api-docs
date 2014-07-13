<?php
/**
 * Interface for how school management systems should determine user's detention.
 */

interface External_UserDetentionInterface {

    /**
     * Retrieves a user's awards.
     *
     * @param mixed    $externalId The student's external ID.
     * @param DateTime $fromDate   The date to fetch from, or null to get all
     *                             in the past.
     * @param DateTime $toDate     The date to fetch until, or null to get all
     *                             in the future.
     * @return array
     */
    public function getAwards($externalId, DateTime $fromDate = null, DateTime $toDate = null);

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
    public function getDetentions($externalId, DateTime $fromDate = null, DateTime $toDate = null);

    /**
     * Retrieves a user's non-attendances that do not have an absence code.
     *
     * @param mixed    $externalId The student's external ID.
     * @param DateTime $fromDate   The date to fetch from, or null to get all
     *                             in the past.
     * @param DateTime $toDate     The date to fetch until, or null to get all
     *                             in the future.
     *
     * @return array
     */
    public function getNonAttendances($externalId, DateTime $fromDate = null, DateTime $toDate = null);

}
