<?php
/**
 * Synergetic implementation of External_UserDetentionInterface
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\DebugBar\Instance as DebugBar;

class External_Synergetic_UserDetention implements External_UserDetentionInterface
{
    use External_Synergetic_ValidateExternalIdTrait;

    /**
     * @var MSSQL
     */
    protected $database;

    public function __construct() {
        $this->database = MSSQL::getInstance();
    }

    /**
     * Dead simple query builder
     *
     * @param  mixed    $externalId
     * @param  string   $dateField
     * @param  DateTime $fromDate
     * @param  DateTime $toDate
     * @return array    0: the where part, 1: the params to use in the quer
     *
     */
    protected function buildWherePart(
        $externalId,
        $dateField,
        DateTime $fromDate = null,
        DateTime $toDate = null
    ) {
        $whereParts = array(
            'ID = :externalId'
        );
        $params = array(
            ':externalId' => "'".$this->database->quote($externalId)."'",
        );

        // Apply date checks
        if ($dateField) {
            if ($fromDate) {
                $whereParts[]        = $dateField.' >= :fromDate';
                $params[':fromDate'] = "'".$this->database->quote($fromDate->format('Y-m-d'))."'";
            }
            if ($toDate) {
                $whereParts[]      = $dateField.' <= :toDate';
                $params[':toDate'] = "'".$this->database->quote($toDate->format('Y-m-d'))."'";
            }
        }

        // Slam the where part in
        $wherePart = '';
        if (!empty($whereParts)) {
            $wherePart = 'WHERE '.implode(' AND ', $whereParts);
        }

        return array($wherePart, $params,);
    }

    /**
     * Retrieves a user's awards.
     *
     * @param mixed $externalId The student's external ID.
     * @param int   $year
     * @param int   $semester
     *
     * @return array
     */
    public function getAwards(
        $externalId,
        $year,
        $semester
    ) {
        // Awards: fetch based on year and semester, not a date span?
        if (!$this->isExternalIdValid($externalId)) {
            return [];
        }
        $query = "
            SELECT
                ID,
                FileYear,
                FileSemester,
                AwardCode,
                AwardDescription,
                CONVERT(VARCHAR(12), AwardDate, 105) AS AwardDate,
                AwardAchievement,
                AwardComment
            FROM dbo.uvSchoLAward
            WHERE ID           = :externalId
            AND   FileYear     = :year
            AND   FileSemester = :semester
        ";
        $query = strtr(
            $query,
            array(
                ':externalId' => "'".$this->database->quote($externalId)."'",
                ':year'       => "'".$this->database->quote($year)."'",
                ':semester'   => "'".$this->database->quote($semester)."'",
            )
        );
//        new Alaress_Probe($query);
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
        if (!$this->isExternalIdValid($externalId)) {
            return [];
        }
        $query = "
            SELECT
                ID,
                CONVERT(VARCHAR(12), DetentionDate, 105) AS DetentionDate,
                DetentionDescription,
                Comments AS DetentionComment
            FROM dbo.uvSchoLDetention
            %s
            ORDER BY DetentionDate DESC
        ";
        list($wherePart, $params) = $this->buildWherePart(
            $externalId, 'DetentionDate', $fromDate, $toDate
        );
        $query = strtr(sprintf($query, $wherePart), $params);
//        new Alaress_Probe($query);
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
        if (!$this->isExternalIdValid($externalId)) {
            return [];
        }
        $query = "
            SELECT
                ID,
                CONVERT(VARCHAR(19), EventDateTime, 120) AS EventDateTime,
                AbsenceTypeCode,
                AbsenceDescription,
                ClassCode,
                ClassCampus,
                ClassDescription,
                AttendanceDayNumber,
                AttendancePeriod,
                LateArrivalFlag,
                CONVERT(VARCHAR(19), LatearrivalTime, 120) AS LateArrivalTime
            FROM dbo.uvSchoLAbsence
            %s
            -- AND AbsenceTypeCode != ''
            ORDER BY EventDateTime DESC
        ";
        list($wherePart, $params) = $this->buildWherePart(
            $externalId, 'EventDateTime', $fromDate, $toDate
        );
        $query = strtr(sprintf($query, $wherePart), $params);
//        new Alaress_Probe($query);
        return $this->database->getAll($query);
    }

}
