<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * HumanEdge implementation of class connection.
 */
class External_Humanedge_Class implements External_ClassInterface
{
    /**
     * @var MSSQL $database
     */
    private $database;

//    /**
//     * @var External_UserInterface
//     */
//    private $externalUser;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
//        $this->externalUser = new External_User('humanedge');
    }

    /**
     * @inheritDoc
     */
    public function getStudents($classCode)
    {
        $query = "
            SELECT
                s.PreferredName AS firstname,
                s.LastName AS lastname,
                s.Year AS StudentYearLevel,
                '' AS StudentHouseDescription,
                '' AS StudentMedicalAlert,
                s.SpecialNeeds AS StudentSpecialNeedsFlag,
                s.Code AS synergy_id,
                '' AS ClassCampus
            FROM Student s
            INNER JOIN ClassDetail cd ON s.ID = cd.StudentID
            INNER JOIN Class cl ON cd.ClassID = cl.ID
            INNER JOIN Subject su ON cl.SubjectID = su.ID
            WHERE (su.Code+'-'+cl.ClassGroup) = {$this->getDatabase()->smartQuote($classCode)}
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * @inheritDoc
     */
    public function getTeacherName($classCode)
    {
        $query = "
            SELECT t.Code
            FROM Staff t
            INNER JOIN ClassStaff ct ON t.ID = ct.StaffID
            INNER JOIN Class cl ON ct.ClassID = cl.ID
            INNER JOIN Subject su ON cl.SubjectID = su.ID
            WHERE (su.Code+'-'+cl.ClassGroup) = {$this->getDatabase()->smartQuote($classCode)}
        ";
        $result = [];
        foreach ($this->getDatabase()->getAll($query) as $row) {
            $result[] = $row['Code'];
        }
        return $result;
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
