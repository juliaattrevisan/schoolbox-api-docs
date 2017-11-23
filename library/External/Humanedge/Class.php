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

    /**
     * Gets the class code part of a query.
     *
     * @param  string $subCode
     * @return array query: the query part, params: the parameters
     */
    protected function getClassCodeQuery($subCode)
    {
        $classQuery   = '(';
        $params       = [];
        $subCodeArray = explode(',', $subCode);
        foreach ($subCodeArray as $i => $code) {
            $classQuery .= "s.Code + ISNULL(b.Suffix, '') = :code{$i} OR ";
            $params['code'.$i] = $code;
        }
        return [
            'query'  => substr($classQuery, 0, -3) . ')',
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

        $classQuery   = $this->getClassCodeQuery($classCode);

        $query = "
            SELECT DISTINCT
                st.FirstName AS firstname,
                st.FamilyName AS lastname,
                '' AS StudentYearLevel,
                '' AS StudentHouseDescription,
                '' AS StudentMedicalAlert,
                '' AS StudentSpecialNeedsFlag,
                '' AS StudentTutorDescription,
                st.Code AS synergy_id,
                '' AS ClassCampus
            FROM tblSubject s
            LEFT  JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblAllocation a on s.ID = a.SubjectID
            INNER JOIN tblPeriod p ON a.PeriodID = p.ID
            INNER JOIN tblStudent st on a.FormID = st.FormID
            WHERE st.deleted = 0
            AND   {$classQuery['query']}

            UNION

            SELECT DISTINCT
                st.FirstName AS firstname,
                st.FamilyName AS lastname,
                '' AS StudentYearLevel,
                '' AS StudentHouseDescription,
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
            AND   {$classQuery['query']}
        ";
        return $db->executeQuery($query, $classQuery['params'])->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getTeacherName($classCode)
    {
        $roomJoin = ($this->requireClassTeachersHaveRooms ? 'INNER JOIN tblRoom r on r.ID = b.RoomID' : '');
        $db       = $this->loadAPlusDatabase();
        $query    = "
            SELECT DISTINCT
                t.Code AS Code
            FROM tblSubject s
            LEFT JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblAllocation a on s.ID = a.SubjectID
            INNER JOIN tblTeacher t on a.TeacherID = t.ID
            $roomJoin
            WHERE t.deleted = 0
            AND   s.Code + ISNULL(b.Suffix, '') = :classCode

            UNION

            SELECT DISTINCT
                t.Code AS Code
            FROM tblSubject s
            INNER JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblTeacher t on b.TeacherID = t.ID
            $roomJoin
            WHERE t.deleted = 0
            AND   s.Code + ISNULL(b.Suffix, '') = :classCode
        ";
        return $db->executeQuery($query, ['classCode' => $classCode])->fetchColumnArray('Code');
    }
}
