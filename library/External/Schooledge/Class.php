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
        $quotedDate = $this->getDatabase()->smartQuote(Alaress_Date_Format::now()->getDatabaseDate());
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
            AND   $quotedDate BETWEEN CONVERT(date, t.StartDate) AND CONVERT(date, t.EndDate)

            ORDER BY synergy_id ASC
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * @inheritDoc
     */
    public function getTeacherName($classCode)
    {
        return [];
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
