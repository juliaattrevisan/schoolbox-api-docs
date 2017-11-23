<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * NGSL specific HumanEdge implementation of class connection.
 */
class External_Humanedge_Ngsl_Class extends External_Humanedge_Class implements External_ClassInterface
{
    use External_Humanedge_APlusDatabaseTrait;

    /**
     * Quotes class codes for direct injection into a SQL query.
     *
     * @param string $classCode
     * @return array query: the query part, params: the parameters
     */
    protected function quoteClassCodes($classCode)
    {
        // Split on ,
        // quote each segment
        // join back together with ,
        $query  = [];
        $params = [];
        foreach (explode(',', $classCode) as $i => $code) {
            $query[]           = ":code{$i}";
            $params['code'.$i] = $code;
        }
        return [
            'query'  => implode(',', $query),
            'params' => $params,
        ];
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
                '' AS yearlevel,
                '' AS house,
                '' AS StudentMedicalAlert,
                '' AS StudentSpecialNeedsFlag,
                '' AS StudentTutorDescription,
                st.Code AS synergy_id,
                '' AS ClassCampus
            FROM tblSubject s
            INNER JOIN tblAllocation a on s.ID = a.SubjectID
            INNER JOIN tblForm f ON a.FormId = f.ID
            INNER JOIN tblPeriod p ON a.PeriodID = p.ID
            INNER JOIN tblStudent st on a.FormID = st.FormID
            WHERE st.deleted = 0
            AND   (s.Code + '_' + RIGHT(f.Code,1)) IN ({$classQuery['query']})

            UNION

            SELECT DISTINCT
                st.FirstName AS firstname,
                st.FamilyName AS lastname,
                '' AS yearlevel,
                '' AS house,
                '' AS StudentMedicalAlert,
                '' AS StudentSpecialNeedsFlag,
                '' AS StudentTutorDescription,
                st.Code AS synergy_id,
                '' AS ClassCampus
            FROM tblSubject s
            INNER JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblSubjectChoice sc ON b.YearBlockItemID = sc.YearBlockItemID
            INNER JOIN tblStudent st ON sc.StudentID = st.ID
            WHERE st.deleted = 0
            AND   (s.Code + ISNULL(b.Suffix, '')) IN ({$classQuery['query']})
        ";
        return $db->executeQuery($query, $classQuery['params'])->fetchAll();
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
            AND   (s.Code + '_' + RIGHT(f.Code,1)) IN ({$classQuery['query']})

            UNION

            SELECT DISTINCT
                t.Code AS Code
            FROM tblSubject s
            INNER JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblTeacher t on b.TeacherID = t.ID
            $electRoomJoin
            WHERE t.deleted = 0
            AND   (s.Code + ISNULL(b.Suffix, '')) IN ({$classQuery['query']})
        ";
        return $db->executeQuery($query, $classQuery['params'])->fetchColumnArray('Code');
    }
}
