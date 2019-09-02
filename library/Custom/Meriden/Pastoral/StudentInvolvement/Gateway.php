<?php
/**
 * Gateway for Meriden queries
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\User;

class Gateway
{
    /**
     * TASS company code.
     *
     * This may be injected later, as it's `config.external_tass_company_code`,
     * but this is not required.
     */
    private const COMPANY_CODE = '01';

    /**
     * Tutor group codes in TASS.
     */
    private const TUTOR_GROUPS = ['9994', '9995', '9996', '9997', '9998', '9999'];

    /**
     * Subjects excluded from a student's list of subjects.
     */
    private const EXCLUDED_SUBJECTS = ['9994', '9995', '9996', '9997', '9998', '9999', '0107', '0074', '4099', '0093', '0103'];

    private const ACTIVITY_TYPE_AWARD = 'C';
    private const ACTIVITY_TYPE_COCURRICULAR = 'B';
    private const ACTIVITY_TYPE_SERVICE = 'O';
    private const ACTIVITY_TYPE_POSITIONS_OF_OFFICE = 'A';

    private const ABSENCE_TYPE_LATE = 'LFS';
    private const ABSENCE_TYPE_DETENTION = 'INF';
    private const ABSENCE_TYPE_ENDORSED_LEAVE = 'AFS';
    private const ABSENCE_TYPES_AWAY = ['AFS', 'APD', 'AAM', 'APM', 'EDP'];

    private const REASON_CODES_AWAY_OR_LATE = ['A', 'L', 'S'];
    private const REASON_CODES_ENDORSED_LEAVE = ['M'];

    /**
     * @var IConnector
     */
    private $database;

    /**
     * Constructor
     *
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
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
    protected function queryDays($year, User $student, array $absenceTypes, array $reasonCodes = [])
    {
        $externalId = $student->getExternalId();
        if (!$externalId) {
            return [];
        }

        $query = "
            SELECT
                studabsent.stud_code,
                studabsent.absent_date,
                studabsent.absent_type,
                studabsent.reas_code
            FROM studabsent
        ";

        $pb = new PredicateBuilder($this->database, 'studabsent');
        $pb = $pb->equals('stud_code', $externalId)
                 ->between('absent_date', $year . '-01-01', $year . '-12-31');

        if (!empty($absenceTypes)) {
            $pb = $pb->in('absent_type', $absenceTypes);
        }
        if (!empty($reasonCodes)) {
            $pb = $pb->in('reas_code', $reasonCodes);
        }

        $query .= " WHERE " . implode(' AND ', $pb->getWhere());

        return $this->database->executeQuery($query, $pb->getParams())->fetchAll();
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
            return [];
        }

        $query = "
            SELECT DISTINCT
                se.stud_code,
                se.act_code,
                ea.act_desc,
                ea.act_type,
                el.lev_desc,
                eu.ud_desc
            FROM ecactivity ea
            INNER JOIN studecact se
                ON se.act_code = ea.act_code
                AND se.cmpy_code = ea.cmpy_code
            LEFT JOIN ecalevel el
                ON el.cmpy_code = se.cmpy_code
                AND el.lev_code = se.act_level
            LEFT JOIN ecudtable eu
                ON eu.ud_code = se.ecud2_code
                AND eu.cmpy_code = se.cmpy_code
                AND eu.ref_code = '2'
        ";

        $pb = new PredicateBuilder($this->database, 'ecactivity');
        $pb = $pb->equals('se.stud_code', $externalId)
                 ->equals('se.act_year', $year)
                 ->equals('ea.act_type', $activityType)
                 ->equals('ea.cmpy_code', self::COMPANY_CODE);

        $query .= " WHERE " . implode(' AND ', $pb->getWhere());

        return $this->database->executeQuery($query, $pb->getParams())->fetchAll();
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
            self::ABSENCE_TYPES_AWAY,
            self::REASON_CODES_AWAY_OR_LATE
        );
        $count     = 0;
        foreach ($resultSet as $row) {
            switch ($row['absent_type']) {
                case self::ABSENCE_TYPE_ENDORSED_LEAVE:
                    $count += 1;
                    break;
                default:
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
            [self::ABSENCE_TYPE_LATE],
            self::REASON_CODES_AWAY_OR_LATE
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
            [self::ABSENCE_TYPE_DETENTION]
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
            [self::ABSENCE_TYPE_ENDORSED_LEAVE],
            self::REASON_CODES_ENDORSED_LEAVE
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
        return [
            'absent'        => $this->getAbsenceCount($year, $student),
            'late'          => $this->getLateCount($year, $student),
            'detentions'    => $this->getDetentionCount($year, $student),
            'endorsedLeave' => $this->getLeaveCount($year, $student),
        ];
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
        return $this->queryActivities($year, $student, self::ACTIVITY_TYPE_AWARD);
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
        return $this->queryActivities($year, $student, self::ACTIVITY_TYPE_COCURRICULAR);
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
        return $this->queryActivities($year, $student, self::ACTIVITY_TYPE_SERVICE);
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
        return $this->queryActivities($year, $student, self::ACTIVITY_TYPE_POSITIONS_OF_OFFICE);
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
        return [
            'awards'       => $this->getAwards($year, $student),
            'cocurricular' => $this->getCocurricular($year, $student),
            'service'      => $this->getService($year, $student),
            'positions'    => $this->getPositions($year, $student),
        ];
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
            return [];
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
        ";

        $pb = new PredicateBuilder($this->database, 'teacher');
        $pb = $pb->equals('studsub.stud_code', $externalId)
                 ->equals('studsub.cmpy_code', self::COMPANY_CODE)
                 ->in('studsub.sub_code', self::TUTOR_GROUPS)
                 ->equals('tchsub.year_num', $year);

        $query .= " WHERE " . implode(' AND ', $pb->getWhere()) . " ORDER BY teacher.tch_name ASC";

        return $this->database->executeQuery($query, $pb->getParams())->fetchAll();
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
            return [];
        }

        // Adapted External_Tass_User->getSubjectList()
        $query = "
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
        ";

        $pb = new PredicateBuilder($this->database, 'studsub');
        $pb = $pb->equals('studsub.stud_code', $externalId)
                 ->in('subtab.sub_code', self::TUTOR_GROUPS)
                 ->equals('studsub.cmpy_code', self::COMPANY_CODE);

        $query .= " WHERE " . implode(' AND ', $pb->getWhere())
            . " ORDER BY studsub.sub_code ASC, studsub.class ASC";

        return $this->database->executeQuery($query, $pb->getParams())->fetchAll();
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
            return [];
        }

        $query = "
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
        ";

        $pb = new PredicateBuilder($this->database, 'studsub');
        $pb = $pb->equals('studsub.stud_code', $externalId)
                 ->equals('studsub.yr_study', $year)
                 ->in('subtab.sub_code', self::TUTOR_GROUPS)
                 ->equals('studsub.cmpy_code', self::COMPANY_CODE);

        $query .= " WHERE " . implode(' AND ', $pb->getWhere())
            . " ORDER BY studsub.sub_code ASC, studsub.class ASC";

        return $this->database->executeQuery($query, $pb->getParams())->fetch();
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
            return [];
        }

        // Adapted External_Tass_User->getSubjectList()
        $query = "
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
        ";

        $pb = new PredicateBuilder($this->database, 'studsub');
        $pb = $pb->equals('studsub.stud_code', $externalId)
                 ->equals('studsub.yr_study', $year)
                 ->notIn('subtab.sub_code', self::EXCLUDED_SUBJECTS)
                 ->equals('studsub.cmpy_code', self::COMPANY_CODE);

        $query .= " WHERE " . implode(' AND ', $pb->getWhere())
            . " ORDER BY subtab.sub_desc ASC";

        // Join all teacher codes into a single result.
        $result = array_reduce(
            $this->database->executeQuery($query, $pb->getParams())->fetchAll(),
            function ($carry, $newRow) {
                $isDuplicateCode = false;
                foreach ($carry as &$row) {
                    if ($row['code'] == $newRow['code']) {
                        $row['tch_code'][] = $newRow['tch_code'];
                        $isDuplicateCode   = true;
                        break;
                    }
                }
                unset($row);
                if (!$isDuplicateCode) {
                    $newRow['tch_code'] = [$newRow['tch_code']];
                    $carry[]            = $newRow;
                }
                return $carry;
            },
            []
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
        list(, $code, $class) = explode('-', $subjectCode);

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
        ";

        $pb = new PredicateBuilder($this->database, 'suboutline');
        $pb = $pb->equals('so.sub_code', $code)
                 ->equals('ts.year_num', $year)
                 ->equals('ts.class', $class)
                 ->equals('so.cmpy_code', self::COMPANY_CODE);

        $query .= " WHERE " . implode(' AND ', $pb->getWhere());

        // Reduce to one result
        $result = [];
        foreach ($this->database->getQueryIterator($query, $pb->getParams()) as $singleResult) {
            if (empty($result)) {
                $result             = $singleResult;
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
        list(, $code, $class) = explode('-', $subjectCode);

        $query = "
            SELECT DISTINCT
                ('-' + RTRIM(st.sub_code) + '-' + RTRIM(ss.class)) AS code
            FROM subtab st
            INNER JOIN studsub ss
                ON st.sub_code = ss.sub_code
                AND st.cmpy_code = ss.cmpy_code
        ";

        $pb = new PredicateBuilder($this->database, 'studsub');
        $pb = $pb->equals('st.sub_code', $code)
                 ->notEquals('ss.class', $class)
                 ->equals('st.cmpy_code', self::COMPANY_CODE);

        $query .= " WHERE " . implode(' AND ', $pb->getWhere());

        return array_map(
            function ($v) {
                return $v['code'];
            },
            $this->database->executeQuery($query, $pb->getParams())->fetchAll()
        );
    }
}
