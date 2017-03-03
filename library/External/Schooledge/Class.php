<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * Schooledge implementation of class connection.
 */
class External_Schooledge_Class extends External_Humanedge_Class implements External_ClassInterface
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
        $this->setDatabase(MSSQL::getInstance());
    }

    /**
     * Gets the class code part of a query.
     *
     * @param  string $subCode
     * @return string
     */
    protected function getClassCodeQuery($subCode)
    {
        $classQuery = '(';
        $subCodeArray = explode(',', $subCode);
        foreach ($subCodeArray as $code) {
            $classQuery .= "ts.Code = {$this->getDatabase()->smartQuote($code)} OR ";
        }
        return substr($classQuery, 0, -3) . ')';
    }

    /**
     * @inheritDoc
     */
    public function getStudents($classCode)
    {
        $classQuery = $this->getClassCodeQuery($classCode);

        // 1st part: blocked classes
        // 2nd part: non-blocked classes
        $query      = "
            SELECT DISTINCT
                s.FirstName    AS firstname,
                s.LastName     AS lastname,
                s.Year         AS StudentYearLevel,
                s.House        AS StudentHouseDescription,
                ''             AS StudentMedicalAlert,
                s.SpecialNeeds AS StudentSpecialNeedsFlag,
                s.Code         AS synergy_id,
                ''             AS ClassCampus
            FROM fc.TimetableSubject ts

            INNER JOIN fc.TimetableSubjectChoice tsc ON ts.ID          = tsc.TimetableSubjectID
            INNER JOIN fc.Timetable              t   ON ts.TimetableID = t.ID
            INNER JOIN dbo.Student               s   ON tsc.PersonID   = s.ID

            WHERE $classQuery
            AND   t.Enabled = 1

            UNION

            SELECT DISTINCT
                s.FirstName    AS firstname,
                s.LastName     AS lastname,
                s.Year         AS StudentYearLevel,
                s.House        AS StudentHouseDescription,
                ''             AS StudentMedicalAlert,
                s.SpecialNeeds AS StudentSpecialNeedsFlag,
                s.Code         AS synergy_id,
                ''             AS ClassCampus

            FROM fc.TimetableSubject ts

            INNER JOIN fc.TimetableClass      tc   ON ts.ID             = tc.TimetableSubjectID
            INNER JOIN fc.YearLevelSub        yls  ON tc.YearLevelSubID = yls.ID
            INNER JOIN fc.YearLevelSubStudent ylss ON yls.ID            = ylss.YearLevelSubID
            INNER JOIN dbo.Student            s    ON ylss.PersonID     = s.ID
            INNER JOIN fc.Timetable           t    ON ts.TimetableID    = t.ID

            WHERE $classQuery
            AND   t.Enabled = 1

            ORDER BY synergy_id ASC
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * @inheritDoc
     */
    public function getTeacherName($classCode)
    {
        $classQuery = $this->getClassCodeQuery($classCode);
        $query      = "
            SELECT DISTINCT tcs_staff.Code AS code
            FROM fc.TimetableSubject ts
            -- Base join to class and timetable
            INNER JOIN fc.TimetableClass tc ON ts.ID          = tc.TimetableSubjectID
            INNER JOIN fc.Timetable      t  ON ts.TimetableID = t.ID
            -- Base relation to staff - is this required?
            INNER JOIN fc.TimetableClassStaff tcs       ON tc.ID        = tcs.TimetableClassId
            INNER JOIN dbo.Staff              tcs_staff ON tcs.PersonID = tcs_staff.ID
            WHERE $classQuery
            AND   t.Enabled = 1

            UNION

            SELECT DISTINCT tcis_staff.Code AS code
            FROM fc.TimetableSubject ts
            -- Base join to class and timetable
            INNER JOIN fc.TimetableClass tc ON ts.ID          = tc.TimetableSubjectID
            INNER JOIN fc.Timetable      t  ON ts.TimetableID = t.ID
            -- Individual relation to staff
            INNER JOIN fc.TimetableClassInstance      tci        ON tc.ID         = tci.TimetableClassID
            INNER JOIN fc.TimetableClassInstanceStaff tcis       ON tci.ID        = tcis.TimetableClassInstanceId
            INNER JOIN dbo.Staff                      tcis_staff ON tcis.PersonID = tcis_staff.ID
            WHERE $classQuery
            AND   t.Enabled = 1

            ORDER BY code ASC
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * @param MSSQL $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }
}
