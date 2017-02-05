<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * NGSL specific HumanEdge implementation of user retrieve methods.
 */

use Schoolbox\User;

class External_Humanedge_Ngsl_User extends External_Humanedge_User implements External_UserInterface
{
    use External_Humanedge_APlusDatabaseTrait;

    /**
     * Returns list of subjects the user is enrolled in
     *
     * @param User $user
     * @return array
     */
    public function getSubjectList(User $user)
    {
        $externalId = $user->getExternalId();

        if (empty($externalId)) {
            return [];
        }

        $quotedId = $this->loadAPlusDatabase()->smartQuote($externalId);
        if ($user->getRole()->isStudent()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $query = "
                SELECT DISTINCT
                    s.Code + '_' + RIGHT(f.Code,1) AS code,
                    s.Name as name,
                    '' AS campusCode,
                    '' AS classType
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                INNER JOIN tblStudent st on a.FormID = st.FormID
                INNER JOIN tblPeriod p ON a.PeriodID = p.ID
                INNER JOIN tblForm f ON a.FormId = f.ID
                WHERE st.deleted = 0
                AND st.code = $quotedId

                UNION

                SELECT DISTINCT
                    s.Code + ISNULL(b.Suffix, '') AS code,
                    s.Name AS name,
                    '' AS campusCode,
                    '' AS classType
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblSubjectChoice sc ON b.YearBlockItemID = sc.YearBlockItemID
                INNER JOIN tblStudent st ON sc.StudentID = st.ID
                WHERE st.deleted = 0
                AND st.code = $quotedId

                ORDER BY code ASC
            ";
        } elseif ($user->getRole()->isStaff()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $coreRoomJoin  = ($this->requireTeacherSubjectsHaveRooms ? 'INNER JOIN tblRoom r on a.RoomID = r.ID' : '');
            $electRoomJoin = ($this->requireTeacherSubjectsHaveRooms ? 'INNER JOIN tblRoom r on b.RoomID = r.ID' : '');
            $query    = "
                SELECT DISTINCT
                    s.Code + '_' + RIGHT(f.Code,1) AS code,
                    s.Name as name,
                    '' AS campusCode,
                    '' AS classType
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                INNER JOIN tblTeacher t on a.TeacherID = t.ID
                INNER JOIN tblForm f ON a.FormId = f.ID
                $coreRoomJoin
                WHERE t.deleted = 0
                AND t.code = $quotedId

                UNION

                SELECT DISTINCT
                    s.Code + ISNULL(b.Suffix, '') as code,
                    s.Name as name,
                    '' AS campusCode,
                    '' AS classType
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblTeacher t on b.TeacherID = t.ID
                $electRoomJoin
                WHERE t.deleted = 0
                AND t.code = $quotedId

                ORDER BY code ASC
            ";
        } else {
            return [];
        }
        return $this->loadAPlusDatabase()->getAll($query);
    }
}
