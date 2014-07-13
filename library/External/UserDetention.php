<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:40 PM
 * To change this template use File | Settings | File Templates.
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
