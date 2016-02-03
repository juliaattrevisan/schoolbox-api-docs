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
    use External_Humanedge_APlusDatabaseTrait;

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
        $db    = $this->loadAPlusDatabase();
        $query = "
            SELECT
                st.FirstName AS firstname,
                st.FamilyName AS lastname,
                '' AS StudentYearLevel,
                '' AS StudentHouseDescription,
                '' AS StudentMedicalAlert,
                '' AS StudentSpecialNeedsFlag,
                st.Code AS synergy_id,
                '' AS ClassCampus
            FROM tblSubject s
            LEFT  JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblAllocation a on s.ID = a.SubjectID
            INNER JOIN tblStudent st on a.FormID = st.FormID
            WHERE st.deleted = 0
            AND   s.Code + ISNULL(b.Suffix, '') = {$db->smartQuote($classCode)}

            UNION ALL

            SELECT
                st.FirstName AS firstname,
                st.FamilyName AS lastname,
                '' AS StudentYearLevel,
                '' AS StudentHouseDescription,
                '' AS StudentMedicalAlert,
                '' AS StudentSpecialNeedsFlag,
                st.Code AS synergy_id,
                '' AS ClassCampus
            FROM tblSubject s
            INNER JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblSubjectChoice sc ON b.YearBlockItemID = sc.YearBlockItemID
            INNER JOIN tblStudent st ON sc.StudentID = st.ID
            WHERE st.deleted = 0
            AND   s.Code + ISNULL(b.Suffix, '') = {$db->smartQuote($classCode)}
        ";
        return $db->getAll($query);
    }

    /**
     * @inheritDoc
     */
    public function getTeacherName($classCode)
    {
        $db    = $this->loadAPlusDatabase();
        $query = "
            SELECT
                t.Code AS Code
            FROM tblSubject s
            LEFT JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblAllocation a on s.ID = a.SubjectID
            INNER JOIN tblTeacher t on a.TeacherID = t.ID
            WHERE t.deleted = 0
            AND   s.Code + ISNULL(b.Suffix, '') = {$db->smartQuote($classCode)}

            UNION ALL

            SELECT
                t.Code AS Code
            FROM tblSubject s
            INNER JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblTeacher t on b.TeacherID = t.ID
            WHERE t.deleted = 0
            AND   s.Code + ISNULL(b.Suffix, '') = {$db->smartQuote($classCode)}
        ";
        $result = [];
        foreach ($db->getAll($query) as $row) {
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
