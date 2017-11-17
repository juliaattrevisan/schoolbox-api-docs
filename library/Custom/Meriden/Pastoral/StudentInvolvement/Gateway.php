<?php
/**
 * Gateway for Meriden queries
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Alaress\Database\Connector\IConnector;
use Schoolbox\User;

class Gateway
{
    /**
     * @var IConnector
     */
    protected $database;

    public function __construct(IConnector $database)
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
     * Gets all attendance related counts.
     *
     * @param int  $year
     * @param User $student
     *
     * @return array
     */
    public function getAllAttendances($year, User $student)
    {
        return array(
            'absent'        => $this->getAbsenceCount($year, $student),
            'late'          => $this->getLateCount($year, $student),
            'detentions'    => $this->getDetentionCount($year, $student),
            'endorsedLeave' => $this->getLeaveCount($year, $student),
        );
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
                teacher.tch_name
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
     * Retrieves all year level groups for this student.
     *
     * @param User $student
     *
     * @return array
     */
    public function getAllYearLevelGroups(User $student)
    {
        $externalId = $student->getExternalId();
        if (!$externalId) {
            return array();
        }
        // Adapted External_Tass_User->getSubjectList()
        $query  = "
            SELECT DISTINCT
                studsub.class,
                studsub.yr_study,
                studsub.sub_code,
                subtab.sub_desc,
                subtab.sub_type,
                ('-' + RTRIM(studsub.sub_code) + '-' + RTRIM(studsub.class)) AS code
            FROM studsub
            INNER JOIN subtab
                ON subtab.sub_code = studsub.sub_code
                AND subtab.cmpy_code = studsub.cmpy_code
            WHERE studsub.stud_code = :externalId
            AND subtab.sub_code IN ('9994','9995','9996','9997','9998','9999')
            AND studsub.cmpy_code = '01'
            ORDER BY
                studsub.sub_code ASC,
                studsub.class ASC
        ";
        $params = array(
            ':externalId' => $this->getDatabase()->smartQuote($externalId),
        );
        $result = $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );
        return $result ?: array();
    }

    /**
     * Given a year level and a student, gets their year level group for that year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return array
     */
    public function getYearLevelGroup($year, User $student)
    {
        $externalId = $student->getExternalId();
        if (!$externalId) {
            return array();
        }
        $query  = "
            SELECT
                studsub.class,
                studsub.yr_study,
                studsub.sub_code,
                subtab.sub_desc,
                subtab.sub_type,
                ('-' + RTRIM(studsub.sub_code) + '-' + RTRIM(studsub.class)) AS code
            FROM studsub
            INNER JOIN subtab
                ON subtab.sub_code = studsub.sub_code
                AND subtab.cmpy_code = studsub.cmpy_code
            WHERE studsub.stud_code = :externalId
            AND studsub.yr_study = :year
            AND subtab.sub_code IN ('9994','9995','9996','9997','9998','9999')
            AND studsub.cmpy_code = '01'
            ORDER BY
                studsub.sub_code ASC,
                studsub.class ASC
        ";
        $params = array(
            ':externalId' => $this->getDatabase()->smartQuote($externalId),
            ':year'       => $this->getDatabase()->smartQuote($year),
        );
        $result = $this->getDatabase()->trimRow(
            $this->getDatabase()->getRow(strtr($query, $params))
        );
        return $result ?: array();
    }

    /**
     * Gets all subjects for this student for this year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return array
     */
    public function getAllSubjectsForYear($year, User $student)
    {
        $externalId = $student->getExternalId();
        if (!$externalId) {
            return array();
        }
        // Adapted External_Tass_User->getSubjectList()
        $query  = "
            SELECT DISTINCT
                studsub.class,
                studsub.yr_study,
                studsub.sub_code,
                subtab.sub_desc,
                subtab.sub_type,
                tchsub.tch_code,
                ('-' + RTRIM(studsub.sub_code) + '-' + RTRIM(studsub.class)) AS code
            FROM studsub
            INNER JOIN subtab
                ON subtab.sub_code = studsub.sub_code
                AND subtab.cmpy_code = studsub.cmpy_code
            LEFT JOIN tchsub
                ON subtab.sub_code = tchsub.sub_code
                AND subtab.cmpy_code = tchsub.cmpy_code
                AND studsub.semester = tchsub.semester
                AND studsub.yr_study = tchsub.year_num
                AND studsub.class = tchsub.class
            WHERE studsub.stud_code = :externalId
            AND subtab.sub_code NOT IN (
                '9994',
                '9995',
                '9996',
                '9997',
                '9998',
                '9999',
                '0107',
                '0074',
                '4099',
                '0093',
                '0103'
            )
            AND studsub.cmpy_code = '01'
            AND studsub.yr_study = :year
            ORDER BY
                subtab.sub_desc ASC
        ";
        $params = array(
            ':externalId' => $this->getDatabase()->smartQuote($externalId),
            ':year'       => $this->getDatabase()->smartQuote($year),
        );
        $result = $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );
        $result = $result ?: array();

        // Join all teacher codes into a single result.
        $result = array_reduce(
            $result,
            function ($carry, $newRow) {
                $isDuplicateCode = false;
                foreach ($carry as &$row) {
                    if ($row['code'] == $newRow['code']) {
                        $row['tch_code'][] = $newRow['tch_code'];
                        $isDuplicateCode = true;
                        break;
                    }
                }
                unset($row);
                if (!$isDuplicateCode) {
                    $newRow['tch_code'] = array($newRow['tch_code']);
                    $carry[] = $newRow;
                }
                return $carry;
            },
            array()
        );
        return $result;
    }

    /**
     * Gets the subject outlines and teacher codes for a given subject code.
     *
     * @param int    $year
     * @param string $subjectCode
     *
     * @return array
     */
    public function getSubject($year, $subjectCode)
    {
        $query = "
            SELECT DISTINCT
                st.sub_desc,
                so.outline_text,
                so.outline_url,
                ts.tch_code
            FROM suboutline so
            INNER JOIN subtab st
                ON so.sub_code=st.sub_code AND so.cmpy_code=st.cmpy_code
            LEFT JOIN tchsub ts
                ON st.sub_code = ts.sub_code AND st.cmpy_code = ts.cmpy_code
            WHERE so.sub_code = :code
            AND   ts.year_num = :year
            AND   ts.class    = :class
            AND   so.cmpy_code = '01'
        ";
        list(, $code, $class) = explode('-', $subjectCode);
        $params = array(
            ':code'  => $this->getDatabase()->smartQuote($code),
            ':year'  => $this->getDatabase()->smartQuote($year),
            ':class' => $this->getDatabase()->smartQuote($class),
        );
        $allResults = $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );

        // Reduce to one result
        $result = [];
        foreach ($allResults as $singleResult) {
            if (empty($result)) {
                $result = $singleResult;
                $result['tch_code'] = [];
            }
            $result['tch_code'][] = $singleResult['tch_code'];
        }
        if (!empty($result['tch_code'])) {
            $result['tch_code'] = array_unique(array_filter($result['tch_code']));
        }
        return $result;
    }

    /**
     * Gets the other possible class codes for related classes.
     *
     * @param string $subjectCode
     *
     * @return array|string[]
     */
    public function getOtherSubjectClasses($subjectCode)
    {
        $query = "
            SELECT DISTINCT
                ('-' + RTRIM(st.sub_code) + '-' + RTRIM(ss.class)) AS code
            FROM subtab st
            INNER JOIN studsub ss
                ON st.sub_code = ss.sub_code
                AND st.cmpy_code = ss.cmpy_code
            WHERE st.sub_code = :code
            AND ss.class != :class
            AND st.cmpy_code = '01';
        ";
        list(, $code, $class) = explode('-', $subjectCode);
        $params = array(
            ':code'  => $this->getDatabase()->smartQuote($code),
            ':class' => $this->getDatabase()->smartQuote($class),
        );
        $result = $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );
        return array_map(
            function ($v) {
                return $v['code'];
            },
            $result ?: array()
        );
    }

    /**
     * @return IConnector
     */
    protected function getDatabase(): IConnector
    {
        return $this->database;
    }

    /**
     * @param  IConnector $database
     *
     * @return $this
     */
    protected function setDatabase(IConnector $database)
    {
        $this->database = $database;
        return $this;
    }
}
