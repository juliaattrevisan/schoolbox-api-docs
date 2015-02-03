<?php
/**
 * Gateway for Meriden queries
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use MSSQL;
use Schoolbox\User;

class Gateway
{
    /**
     * @var MSSQL
     */
    protected $database;

    public function __construct(MSSQL $database)
    {
        // Set up required mappers, DB access, etc. here.
        $this->setDatabase($database);
    }


    /**
     * Base query for querying a student's absences.
     *
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
        $result = $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );
        return $result ?: array();
    }

    /**
     * Base query for querying a student's activities.
     *
     * @param int    $year
     * @param User   $student
     * @param string $activityType
     *
     * @return array
     */
    protected function queryActivities($year, User $student, $activityType)
    {
        $externalId = $student->getExternalId();
        if (!$externalId) {
            return array();
        }
        $query = "
            SELECT DISTINCT
                studecact.stud_code,
                studecact.act_code,
                ecactivity.act_desc,
                ecactivity.act_type
            FROM ecactivity
            INNER JOIN studecact
                ON studecact.act_code = ecactivity.act_code
                AND studecact.cmpy_code = ecactivity.cmpy_code
            WHERE studecact.stud_code = :externalId
            AND studecact.act_year = :year
            AND ecactivity.act_type = :activityType
            AND ecactivity.cmpy_code = '01'
        ";
        $params = array(
            ':externalId'   => $this->getDatabase()->smartQuote($externalId),
            ':year'         => $this->getDatabase()->smartQuote($year),
            ':activityType' => $this->getDatabase()->smartQuote($activityType),
        );
        $result = $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );
        return $result ?: array();
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
     * Gets awards for the given student.
     *
     * @param      $year
     * @param User $student
     *
     * @return array
     */
    public function getAwards($year, User $student)
    {
        return $this->queryActivities($year, $student, 'C');
    }

    /**
     * Gets co-curricular activities for the given student.
     *
     * @param      $year
     * @param User $student
     *
     * @return array
     */
    public function getCocurricular($year, User $student)
    {
        return $this->queryActivities($year, $student, 'B');
    }

    /**
     * Gets community service for the given student.
     *
     * @param      $year
     * @param User $student
     *
     * @return array
     */
    public function getService($year, User $student)
    {
        return $this->queryActivities($year, $student, 'O');
    }

    /**
     * Gets positions of offices for the given student.
     *
     * @param      $year
     * @param User $student
     *
     * @return array
     */
    public function getPositions($year, User $student)
    {
        return $this->queryActivities($year, $student, 'A');
    }

    /**
     * Gets all activities (awards, co-curricular, community service, positions
     * of office).
     *
     * @param int  $year
     * @param User $student
     *
     * @return array
     */
    public function getAllActivities($year, User $student)
    {
        return array(
            'awards'       => $this->getAwards($year, $student),
            'cocurricular' => $this->getCocurricular($year, $student),
            'service'      => $this->getService($year, $student),
            'positions'    => $this->getPositions($year, $student),
        );
    }

    /**
     * Gets all tutors this student may have had through the year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return array
     */
    public function getAllTutors($year, User $student)
    {
        $externalId = $student->getExternalId();
        if (!$externalId) {
            return array();
        }
        // In Schoolbox-Tass nomeclature, Schoolbox classes will have the
        // following code:
        // {tchsub.year_num(optional)}-{tchsub.sub_code}-{tchsub.class}
        $query = "
            SELECT DISTINCT
                teacher.tch_code,
                teacher.tch_name,
                tchsub.semester
            FROM teacher
            INNER JOIN tchsub
                ON tchsub.tch_code = teacher.tch_code
                AND tchsub.cmpy_code = teacher.cmpy_code
            INNER JOIN studsub
                ON studsub.sub_code = tchsub.sub_code
                AND studsub.class = tchsub.class
                AND studsub.yr_study = tchsub.year_num
                AND studsub.semester = tchsub.semester
                AND studsub.cmpy_code = tchsub.cmpy_code
            INNER JOIN subtab
                ON subtab.sub_code = studsub.sub_code
                AND subtab.cmpy_code = studsub.cmpy_code
            WHERE studsub.stud_code = :externalId
            AND studsub.cmpy_code = '01'
            AND subtab.sub_code IN ('9994','9995','9996','9997','9998','9999')
            AND tchsub.year_num = :year
            ORDER BY
                tchsub.semester ASC,
                teacher.tch_name ASC
        ";
        $params = array(
            ':externalId' => $this->getDatabase()->smartQuote($externalId),
            ':year'       => $this->getDatabase()->smartQuote($year),
        );
        $result = $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );
        return $result ?: array();
    }

    /**
     * @return MSSQL
     */
    protected function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param  MSSQL $database
     *
     * @return $this
     */
    protected function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }
}
