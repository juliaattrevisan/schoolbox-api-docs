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

    /**
     * By default, class-teacher relations require that the timetable entry
     * has a room attached to it.
     *
     * @var bool
     */
    protected $requireClassTeachersHaveRooms = true;

//    /**
//     * @var External_UserInterface
//     */
//    private $externalUser;
//
//    /**
//     * Constructor
//     */
//    public function __construct()
//    {
//        $this->setDatabase(MSSQL::getInstance());
////        $this->externalUser = new External_User('humanedge');
//    }

    /**
     * Gets the class code part of a query.
     *
     * @param  string $subCode
     * @return string
     */
    protected function getClassCodeQuery($subCode)
    {
        $db    = $this->loadAPlusDatabase();
        $classQuery = '(';
        $subCodeArray = explode(',', $subCode);
        foreach ($subCodeArray as $code) {
            $classQuery .= "s.Code + ISNULL(b.Suffix, '') = {$db->smartQuote($code)} OR ";
        }
        return substr($classQuery, 0, -3) . ')';
    }

    /**
     * Quotes class codes for direct injection into a SQL query.
     *
     * @param string $classCode
     * @return string
     */
    protected function quoteClassCodes($classCode)
    {
        // Split on ,
        // quote each segment
        // join back together with ,
        return implode(
            ',',
            array_map(
                [$this->loadAPlusDatabase(), 'smartQuote'],
                explode(',', $classCode)
            )
        );
    }


    /**
     * @inheritDoc
     */
    public function getStudents($classCode)
    {
        $db    = $this->loadAPlusDatabase();
        // Period join is required even though it's unused - this filters out
        // students who actually don't have periods for this class.

        $classQuery = $this->quoteClassCodes($classCode);

        $query = "
            SELECT DISTINCT
                st.FirstName AS firstname,
                st.FamilyName AS lastname,
                '' AS StudentYearLevel,
                '' AS StudentHouseDescription,
                '' AS StudentMedicalAlert,
                '' AS StudentSpecialNeedsFlag,
                st.Code AS synergy_id,
                '' AS ClassCampus
            FROM tblSubject s
            INNER JOIN tblAllocation a on s.ID = a.SubjectID
            INNER JOIN tblForm f ON a.FormId = f.ID
            INNER JOIN tblPeriod p ON a.PeriodID = p.ID
            INNER JOIN tblStudent st on a.FormID = st.FormID
            WHERE st.deleted = 0
            AND   (s.Code + '_' + RIGHT(f.Code,1)) IN ($classQuery)

            UNION

            SELECT DISTINCT
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
            AND   (s.Code + ISNULL(b.Suffix, '')) IN ($classQuery)
        ";
        return $db->getAll($query);
    }

    /**
     * @inheritDoc
     */
    public function getTeacherName($classCode)
    {
        $coreRoomJoin  = ($this->requireClassTeachersHaveRooms ? 'INNER JOIN tblRoom r on a.RoomID = r.ID' : '');
        $electRoomJoin = ($this->requireClassTeachersHaveRooms ? 'INNER JOIN tblRoom r on b.RoomID = r.ID' : '');
        $db            = $this->loadAPlusDatabase();
        $classQuery    = $this->quoteClassCodes($classCode);
        $query         = "
            SELECT DISTINCT
                t.Code AS Code
            FROM tblSubject s
            INNER JOIN tblAllocation a on s.ID = a.SubjectID
            INNER JOIN tblForm f ON a.FormId = f.ID
            INNER JOIN tblTeacher t on a.TeacherID = t.ID
            $coreRoomJoin
            WHERE t.deleted = 0
            AND   (s.Code + '_' + RIGHT(f.Code,1)) IN ($classQuery)

            UNION

            SELECT DISTINCT
                t.Code AS Code
            FROM tblSubject s
            INNER JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblTeacher t on b.TeacherID = t.ID
            $electRoomJoin
            WHERE t.deleted = 0
            AND   (s.Code + ISNULL(b.Suffix, '')) IN ($classQuery)
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
