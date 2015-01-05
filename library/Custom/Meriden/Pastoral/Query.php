<?php
/**
 * Queries for the Meriden pastoral system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Custom\Meriden\Pastoral;

use MSSQL;
use Schoolbox\User;

class Query
{
    /**
     * @var MSSQL
     */
    protected $database;

    /**
     * Constructor
     *
     * @param MSSQL $database
     */
    public function __construct(MSSQL $database)
    {
        $this->setDatabase($database);
    }

    /**
     * @param int   $year
     * @param User  $student
     * @param array $absenceTypes
     * @param array $reasonCodes
     *
     * @return array
     */
    protected function queryDays($year, User $student, array $absenceTypes, array $reasonCodes = array())
    {
        $externalId = $student->getExternalId();
        if (!$externalId) {
            return array();
        }
        $query = "
            SELECT
                studabsent.stud_code,
                studabsent.absent_date,
                studabsent.absent_type,
                studabsent.reas_code
            FROM studabsent
            WHERE stud_code = :externalId
            AND absent_date BETWEEN :yearStart AND :yearEnd
        ";
        $params = array(
            ':externalId' => $this->getDatabase()->smartQuote($externalId),
            ':yearStart'  => $this->getDatabase()->smartQuote($year . '-01-01'),
            ':yearEnd'    => $this->getDatabase()->smartQuote($year . '-12-31'),
        );
        if (!empty($absenceTypes)) {
            $query .= ' AND absent_type IN (:absenceTypes) ';
            $params[':absenceTypes'] = implode(
                ',',
                array_map(
                    array($this->getDatabase(), 'smartQuote'),
                    $absenceTypes
                )
            );
        }
        if (!empty($reasonCodes)) {
            $query .= ' AND reas_code IN (:reasonCodes) ';
            $params[':reasonCodes'] = implode(
                ',',
                array_map(
                    array($this->getDatabase(), 'smartQuote'),
                    $reasonCodes
                )
            );
        }
        return $this->getDatabase()->getAll(strtr($query, $params));
    }

    /**
     * Gets the number of days that this student was absent for this year.
     *
     * The following absence types add the following amount to days absent:
     * * AFS: 1
     * * APD, AAM, APM, EDP: 0.5
     *
     * @param int  $year
     * @param User $student
     *
     * @return float
     */
    public function getAbsenceCount($year, User $student)
    {
        $resultSet = $this->queryDays(
            $year,
            $student,
            array('AFS', 'APD', 'AAM', 'APM', 'EDP'),
            array('A', 'L', 'S')
        );
        $count = 0;
        foreach ($resultSet as $row) {
            switch ($row['absent_type']) {
                case 'AFS':
                    $count += 1;
                    break;
                case 'APD':
                case 'AAM':
                case 'APM':
                case 'EDP':
                    $count += 0.5;
                    break;
            }
        }
        return $count;
    }

    /**
     * Gets the number of days that this student was late for this year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return int
     */
    public function getLateCount($year, User $student)
    {
        $resultSet = $this->queryDays(
            $year,
            $student,
            array('LFS'),
            array('A', 'L', 'S')
        );
        return count($resultSet);
    }

    /**
     * Gets the number of detentions that this student has had this year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return int
     */
    public function getDetentionCount($year, User $student)
    {
        $resultSet = $this->queryDays(
            $year,
            $student,
            array('DET')
        );
        return count($resultSet);
    }

    /**
     * Gets the number of days endorsed leave that this student has had this year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return int
     */
    public function getLeaveCount($year, User $student)
    {
        $resultSet = $this->queryDays(
            $year,
            $student,
            array('AFS'),
            array('M')
        );
        return count($resultSet);
    }

    /**
     * @return MSSQL
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param  MSSQL $database
     *
     * @return $this
     */
    public function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }
}
