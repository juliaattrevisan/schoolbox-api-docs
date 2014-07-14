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
        // Awards: based on year and semester, not a date span?
        $query = "SELECT TOP 10 * FROM SynergyOne.dbo.uvSchoLAward";
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
        $query = "
            SELECT
                ID,
                CONVERT(VARCHAR(12), DetentionDate, 105) AS DetentionDate,
                DetentionDescription,
                Comments
            FROM SynergyOne.dbo.uvSchoLDetention
            %s
            ORDER BY DetentionDate DESC
        ";
        $whereParts = array(
            'ID = :externalId'
        );
        $params = array(
            ':externalId' => "'".$this->database->quote($externalId)."'",
        );

        // Apply date checks
        if ($fromDate) {
            $whereParts[]        = 'DetentionDate >= :fromDate';
            $params[':fromDate'] = "'".$this->database->quote($fromDate->format('Y-m-d'))."'";
        }
        if ($toDate) {
            $whereParts[]      = 'DetentionDate <= :toDate';
            $params[':toDate'] = "'".$this->database->quote($toDate->format('Y-m-d'))."'";
        }

        // Slam the where part in
        $wherePart = '';
        if (!empty($whereParts)) {
            $wherePart = 'WHERE '.implode(' AND ', $whereParts);
        }
        $query = strtr(sprintf($query, $wherePart), $params);
        return $this->database->getAll($query);
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
        $query = "
            SELECT
                ID,
                EventDateTime,
                AbsenceTypeCode,
                AbsenceDescription,
                ClassCode,
                ClassCampus,
                ClassDescription,
                AttendanceDayNumber,
                AttendancePeriod,
                LateArrivalFlag,
                LatearrivalTime
            FROM SynergyOne.dbo.uvSchoLAbsence
            %s
            ORDER BY DetentionDate DESC
        ";
        return $this->database->getAll($query);
    }

}
