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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Query
{
    /**
     * @var MSSQL
     */
    protected $database;

    /**
     * @var CategoryMapper
     */
    protected $categoryMapper;

    /**
     * Constructor
     *
     * @param MSSQL          $database
     * @param CategoryMapper $categoryMapper
     */
    public function __construct(MSSQL $database, CategoryMapper $categoryMapper)
    {
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
        return $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );
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
        return $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );
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
        return $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );
    }

    /**
     * Adds year level (7-12) into the year level group record.
     *
     * @param array $group
     *
     * @return array
     */
    protected function augmentYearLevelGroupWithYearLevel($group)
    {
        // All year groups are named "Year x Tutor Group"
        preg_match('/^Year (?P<year_level>\\d+) Tutor Group$/', $group['sub_desc'], $subject);
        $group['year_level'] = $subject['year_level'];
        return $group;
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
        $query = "
            SELECT DISTINCT
                studsub.class,
                studsub.yr_study,
                studsub.sub_code,
                subtab.sub_desc,
                subtab.sub_type
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
        $result = array_map(
            array($this, 'augmentYearLevelGroupWithYearLevel'),
            $result
        );
        return array_map(
            array($this, 'augmentSubjectWithCode'),
            $result
        );
    }

    /**
     * Given a year level and a student, gets their year level group for that year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return int
     */
    public function getYearLevelGroup($year, User $student)
    {
        $externalId = $student->getExternalId();
        if (!$externalId) {
            return array();
        }
        $query = "
            SELECT
                studsub.class,
                studsub.yr_study,
                studsub.sub_code,
                subtab.sub_desc,
                subtab.sub_type
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

        return $this->augmentYearLevelGroupWithYearLevel(
            $this->augmentSubjectWithCode($result)
        );
    }

    /**
     * Given a year level and a student, gets their year level for that year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return int
     */
    public function getYearLevelForStudentForYear($year, User $student)
    {
        $subject = $this->getYearLevelGroup($year, $student);
        return $subject['year_level'];
    }

    /**
     * Adds Schoolbox compatible subject code into the subject record.
     *
     * @param array $subject
     *
     * @return array
     */
    protected function augmentSubjectWithCode($subject)
    {
        $subject['code'] = sprintf(
            '-%s-%s',
            $subject['sub_code'],
            $subject['class']
        );
        return $subject;
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
        $query = "
            SELECT DISTINCT
                studsub.class,
                studsub.yr_study,
                studsub.sub_code,
                subtab.sub_desc,
                subtab.sub_type,
                tchsub.tch_code
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
            AND subtab.sub_code NOT IN ('9994','9995','9996','9997','9998','9999')
            AND studsub.cmpy_code = '01'
            AND studsub.yr_study = :year
            ORDER BY
                subtab.sub_desc ASC
        ";
        $params = array(
            ':externalId'   => $this->getDatabase()->smartQuote($externalId),
            ':year'         => $this->getDatabase()->smartQuote($year),
        );
        $result = $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll(strtr($query, $params))
        );

        // Add class code
        // year level is the student's year level at the time they took class,
        // not the year in which they took it.
        $result = array_map(
            array($this, 'augmentSubjectWithCode'),
            $result
        );

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
     * Augments a list retrieved by getAllSubjectsForYear() with a homepage
     * link and one or more teachers.

     * @param array $subjects
     * @return array
     */
    public function augmentSubjectsWithTeacher(array $subjects)
    {
        $userMapper = new UserMapper();
        foreach ($subjects as &$subject) {
            // Get every teacher by their tch_code
            $subject['teachers'] = $userMapper->findByColumn('synergy_id', $subject['tch_code']);
        }
        unset($subject);
        return $subjects;
    }

    /**
     * Gets the subject outlines for a given subject code.
     *
     * @param string $subjectCode
     *
     * @return array
     */
    public function getSubject($subjectCode)
    {
        $query = "
            SELECT
                st.sub_desc,
                so.outline_text,
                so.outline_url
            FROM suboutline so
            INNER JOIN subtab st ON so.sub_code=st.sub_code AND so.cmpy_code=st.cmpy_code
            WHERE so.sub_code = :code
        ";
        $params = array(
            ':code' => $this->getDatabase()->smartQuote($subjectCode),
        );
        return $this->getDatabase()->trimRow(
            $this->getDatabase()->getRow(strtr($query, $params))
        );
    }

    /**
     * Gets the (current or archived) folder for this subject code,
     * or an empty one if not found.
     *
     * @param $subjectCode
     *
     * @return Category
     */
    public function getFolder($subjectCode)
    {
        try {
            return $this->getCategoryMapper()->findByCode($subjectCode);
        } catch (MapperException $e) {
            // This folder might have been rolled over.
            // Multiple rolled-over categories with this code could exist,
            // get the one that has the requested student in it.
            // To do this, every matching category has to be checked.
            try {
                return $this->getCategoryMapper()->findByArchivedCode($subjectCode);
            } catch (MapperException $e) {
                return new Category();
            }
        }
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

    /**
     * @return CategoryMapper
     */
    public function getCategoryMapper()
    {
        return $this->categoryMapper;
    }

    /**
     * @param  CategoryMapper $categoryMapper
     *
     * @return $this
     */
    public function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }
}
