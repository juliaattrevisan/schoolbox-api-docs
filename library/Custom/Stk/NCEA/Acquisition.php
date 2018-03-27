<?php
/**
 * Acquisition
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Stk\NCEA;

use Alaress\Database\Connector\IConnector;


class Acquisition
{

    /**
     * @var IConnector
     */
    private $database;

    /**
     * The ExternalId of the student
     *
     * @var int
     */
    private $externalId;

    /**
     * Controller constructor.
     *
     * @param IConnector $database
     * @param int        $externalId
     */
    public function __construct(IConnector $database, int $externalId)
    {
        $this->database = $database;
        $this->externalId = $externalId;
    }


    /**
     * Get all the NCEA data for $externalId
     *
     * Their view contains multiple years of data; it isn't just the current year's data,
     * it's a person's entire history
     *
     * Filter it for relevant values (see WHERE clause)
     *
     * @param int $year Get records only for $year
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function getRecords(int $year): array
    {
        $statement = $this->database->prepareQuery('
            SELECT 
                R.FileYear,
                R.ID,
                R.StudentPreferred,
                R.StudentName,
                R.StudentTutor,
                R.Grade,
                R.AssessAreaNCEAStandardNumber,
                R.AssessAreaNCEALevel,
                R.AssessAreaNCEACredits,
                R.AssessmentOption,
                R.CreditsGained,
                R.SubjectSort,
                R.SubjectName,
                R.CommPortalCurrentFlag
            FROM dbo.uvStudentsNCEAResultsTracking R
            WHERE R.ID = :externalId
            AND CommPortalCurrentFlag = 1
            AND FileYear = :year
            ORDER BY R.SubjectSort, R.AssessAreaNCEAStandardNumber
        ');

        $statement->execute([
            'externalId' => $this->externalId,
            'year' => $year,
        ]);

        /* Passed to Aggregation for summarising
         */
        $data = [

            /* Headings that are repeated for every row
             * But we only need them once
             */
            'meta' => [],

            /* Individual data records that we need to Aggregate later
             */
            'records' => [],
        ];

        while ($row = $statement->fetch()) {
            if (empty($data['meta'])) {
                $data['meta'] = [
                    'externalId' => $row['ID'],
                    'firstname' => $row['StudentPreferred'],
                    'fullname' => $row['StudentName'],
                    'tutorGroup' => $row['StudentTutor'],
                    'year' => $row['FileYear'],
                ];
            }

            $data['records'][] = [
                'grade' => $row['Grade'],
                'nceaStandardNumber' => $row['AssessAreaNCEAStandardNumber'],
                'nceaLevel' => $row['AssessAreaNCEALevel'],
                'nceaCredits' => $row['AssessAreaNCEACredits'],
                'assessmentOption' => $row['AssessmentOption'],
                'creditsGained' => $row['CreditsGained'],
                'subjectSort' => $row['SubjectSort'],
                'subjectName' => $row['SubjectName'],
            ];
        }

        return $data;
    }
}