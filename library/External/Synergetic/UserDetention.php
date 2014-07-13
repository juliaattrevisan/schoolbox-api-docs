<?php
/**
 * Synergetic implementation of External_UserDetentionInterface
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\DebugBar\Instance as DebugBar;

class External_Synergetic_UserDetention implements External_UserDetentionInterface {

    public function __construct() {
        $this->database = MSSQL::getInstance();
        if (DebugBar::isEnabled()) {
            $this->database->installCollector();
            /** @var \DebugBar\DataCollector\DataCollectorInterface $collector */
            $collector = $this->database->getCollector();
            if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                DebugBar::getInstance()->addCollector($collector);
            }
        }
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
        $query = "SELECT TOP 10 * FROM SynergyOne.dbo.uvSchoolAward";
        return $this->database->getAll($query);
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
        $query = "SELECT TOP 10 * FROM SynergyOne.dbo.uvSchoolDetention";
        return $this->database->getAll($query);
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
        $query = "SELECT TOP 10 * FROM SynergyOne.dbo.uvScholAbsences";
        return $this->database->getAll($query);
    }

}
