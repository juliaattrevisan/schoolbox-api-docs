<?php
/**
 * Schooledge implementation of class connection.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
use Schoolbox\CampusMapper;

class External_Schooledge_Class implements External_ClassInterface
{


    /**
     * @var MSSQL
     */
    private $database;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
    }


    /**
     * Gets the class code part of a query.
     *
     * @param  string $subCode
     * @return string
     */
    protected function getClassCodeQuery($subCode): string
    {
        $classQuery = '(';
        $subCodeArray = explode(',', $subCode);
        foreach ($subCodeArray as $code) {
            if (strpos($code, '/') !== false) {
                $codeArray = explode('/', $code, 2);
                $classQuery .= "(tc.Name = '{$this->database->quote($codeArray[1])}' AND t.Name = '{$this->database->quote($codeArray[0])}' ) OR ";
            } else {
                $classQuery .= "tc.Name = '{$this->database->quote($code)}' OR ";
            }
        }
        return substr($classQuery, 0, -3) . ')';
    }


    /**
     * Get a query string that contains all the timetable codes
     * @return null|string
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function getTimetableCodes(): ?string
    {
        $allCampusCodes = CampusMapper::getInstance()->findDistinct('ext_timetable_code');
        $quotedCode     = [];
        foreach ($allCampusCodes as $code) {
            if (strlen($code) > 0) {
                $quotedCode[$code] = "'" . $this->database->quote($code) . "'";
            }
        }

        if (count($quotedCode)) {
            return 'IN(' . implode(',', $quotedCode) . ')';
        }
        return null;
    }


    /**
     * @inheritDoc
     */
    public function getStudents(string $classCode): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $timetableCodes = $this->getTimetableCodes();
        if (null === $timetableCodes) {
            return [];
        }

        $classQuery = $this->getClassCodeQuery($classCode);

        // 1st part: blocked classes
        // 2nd part: non-blocked classes
        $query      = "
            SELECT DISTINCT
                s.FirstName    AS firstname,
                s.LastName     AS lastname,
                s.Year         AS StudentYearLevel,
                s.House        AS StudentHouseDescription,
                s.MedicalConditions AS StudentMedicalAlert,
                s.SpecialNeeds AS StudentSpecialNeedsFlag,
                ''             AS EnglishSecondFlag,
                s.HomeGroup    AS StudentTutorDescription,
                s.Code         AS synergy_id
            FROM fc.TimetableSubject ts

            INNER JOIN fc.Timetable              t   ON ts.TimetableID = t.ID
            INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
            INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID
            INNER JOIN fc.TimetableSubjectChoice tsc  ON tc.ID             = tsc.TimetableClassID
            INNER JOIN dbo.Student               s    ON tsc.PersonID      = s.PersonID

            WHERE $classQuery
            AND   t.Enabled = 1
            AND   t.Name    {$timetableCodes}

            UNION

            SELECT DISTINCT
                s.FirstName    AS firstname,
                s.LastName     AS lastname,
                s.Year         AS StudentYearLevel,
                s.House        AS StudentHouseDescription,
                s.MedicalConditions AS StudentMedicalAlert,
                s.SpecialNeeds AS StudentSpecialNeedsFlag,
                ''             AS EnglishSecondFlag,
                s.HomeGroup    AS StudentTutorDescription,
                s.Code         AS synergy_id

            FROM fc.TimetableSubject ts

            INNER JOIN fc.TimetableClass      tc   ON ts.ID             = tc.TimetableSubjectID
            INNER JOIN fc.YearLevelSub        yls  ON tc.YearLevelSubID = yls.ID
            INNER JOIN fc.YearLevelSubStudent ylss ON yls.ID            = ylss.YearLevelSubID
            INNER JOIN dbo.Student            s    ON ylss.PersonID     = s.PersonID
            INNER JOIN fc.Timetable           t    ON ts.TimetableID    = t.ID

            WHERE $classQuery
            AND   t.Enabled = 1
            AND   t.Name    {$timetableCodes}

            ORDER BY synergy_id ASC
        ";
        return $this->database->getAll($query);
    }


    /**
     * @inheritDoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $timetableCodes = $this->getTimetableCodes();
        if (null === $timetableCodes) {
            return [];
        }

        $classQuery = $this->getClassCodeQuery($classCode);
        $teachers = [];

        // As far as I can interpret:
        // Use base staff if there is no TimetableClassStaff in TimetableClassInstanceStaff
        // Use use replacement staff if it's found
        $query      = "
            SELECT DISTINCT
                staff_base.Code AS code
            FROM fc.TimetableSubject ts

            -- Base join to timetable
            INNER JOIN fc.Timetable t ON ts.TimetableID = t.ID

            -- Base join to class
            INNER JOIN fc.TimetableClass tc ON ts.ID = tc.TimetableSubjectID

            -- Base staff
            INNER JOIN fc.TimetableClassStaff tcs        ON tc.ID        = tcs.TimetableClassId
            INNER JOIN dbo.Staff              staff_base ON tcs.PersonID = staff_base.PersonID

            -- Override staff - not used in this part, but need to ensure there is no override
            LEFT  JOIN fc.TimetableClassInstance      tci  ON tc.ID  = tci.TimetableClassID
            LEFT  JOIN fc.TimetableClassInstanceStaff tcis ON tci.ID = tcis.TimetableClassInstanceId

            WHERE $classQuery
            AND   tcis.TimetableClassStaffID IS NULL
            AND   staff_base.Code IS NOT NULL
            AND   t.Name    {$timetableCodes}
            AND   t.Enabled = 1

            UNION

            SELECT DISTINCT
                staff_replacement.Code AS code
            FROM fc.TimetableSubject ts

            -- Base join to timetable
            INNER JOIN fc.Timetable t ON ts.TimetableID = t.ID

            -- Base join to class
            INNER JOIN fc.TimetableClass tc ON ts.ID = tc.TimetableSubjectID

            -- Override staff
            INNER JOIN fc.TimetableClassInstance      tci               ON tc.ID         = tci.TimetableClassID
            LEFT  JOIN fc.TimetableClassInstanceStaff tcis              ON tci.ID        = tcis.TimetableClassInstanceId
            LEFT  JOIN dbo.Staff                      staff_replacement ON tcis.PersonID = staff_replacement.PersonID

            WHERE $classQuery
            AND   staff_replacement.Code IS NOT NULL
            AND   t.Enabled = 1
            AND   t.Name    {$timetableCodes}

            ORDER BY code ASC
        ";

        $statement = $this->database->query($query);
        while ($teacher = $statement->fetch()) {
            $teachers[] = $teacher['code'];
        }

        return array_unique($teachers);
    }


    /**
     * @return array
     */
    public function findClassTypes(): array
    {
        return [];
    }


    /**
     * @param string $filetype
     * @param array  $timetableCode
     *
     * @return array
     */
    public function findClassesByType(string $filetype, array $timetableCode): array
    {
        return [];
    }
}
