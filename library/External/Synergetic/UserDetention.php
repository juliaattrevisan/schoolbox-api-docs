<?php

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;

/**
 * Synergetic implementation of External_UserDetentionInterface
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class External_Synergetic_UserDetention implements External_UserDetentionInterface
{
    use External_Synergetic_ValidateExternalIdTrait;

    /**
     * @var IConnector
     */
    protected $database;

    public function __construct() {
        $this->database = Factory::getInstanceExternal();
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
            ':externalId' => $this->database->legacyQuote($externalId),
        );

        // Apply date checks
        if ($dateField) {
            if ($fromDate) {
                $whereParts[]        = $dateField . ' >= :fromDate';
                $params[':fromDate'] = $this->database->legacyQuote($fromDate->format('Y-m-d'));
            }
            if ($toDate) {
                $whereParts[]      = $dateField . ' <= :toDate';
                $params[':toDate'] = $this->database->legacyQuote($toDate->format('Y-m-d'));
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
     * @inheritDoc
     */
    public function getAwards(string $externalId, string $year, string $semester): array
    {
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
                ':externalId' => $this->database->legacyQuote($externalId),
                ':year'       => $this->database->legacyQuote($year),
                ':semester'   => $this->database->legacyQuote($semester),
            )
        );

        return $this->database->getAll($query);
    }

    /**
     * @inheritDoc
     */
    public function getDetentions(string $externalId, DateTime $fromDate = null, DateTime $toDate = null): array
    {
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
        [$wherePart, $params] = $this->buildWherePart(
            $externalId, 'DetentionDate', $fromDate, $toDate
        );
        $query = strtr(sprintf($query, $wherePart), $params);

        return $this->database->getAll($query);
    }

    /**
     * @inheritDoc
     */
    public function getAbsences(string $externalId, DateTime $fromDate = null, DateTime $toDate = null): array
    {
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
        [$wherePart, $params] = $this->buildWherePart(
            $externalId, 'EventDateTime', $fromDate, $toDate
        );
        $query = strtr(sprintf($query, $wherePart), $params);

        return $this->database->getAll($query);
    }

}
