<?php
/**
 * Wrapper class for user detention external connectors.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_UserDetention implements External_UserDetentionInterface {

    /**
     * @var External_UserDetentionInterface
     */
    protected $database;

    /**
     * Constructor
     *
     * @throws Exception
     * @param String $databaseName
     */
    public function __construct($databaseName = '') {
        $factory = new External_DatabaseFactory();
        $this->database = $factory->buildUserDetentionConnector($databaseName);
    }

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
        return $this->database->getAwards($externalId, $fromDate, $toDate);
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
        return $this->database->getDetentions($externalId, $fromDate, $toDate);
    }

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
    public function getNonAttendances(
        $externalId,
        DateTime $fromDate = null,
        DateTime $toDate = null
    ) {
        return $this->database->getNonAttendances($externalId, $fromDate, $toDate);
    }

}
