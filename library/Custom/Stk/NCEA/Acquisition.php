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
     * @throws \Alaress\Database\Exception
     */
    public function getRecords()
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
            ORDER BY R.FileYear, R.SubjectSort, R.AssessAreaNCEAStandardNumber
        ');

        $statement->execute([
            'externalId' => $this->externalId
        ]);

        while ($row = $statement->fetch()) {
            new \Alaress_Probe($row);
        }
    }
}