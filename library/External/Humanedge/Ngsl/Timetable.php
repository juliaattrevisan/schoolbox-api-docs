<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 *
 * NGSL specific HumanEdge implementation of timetable.
 */
class External_Humanedge_Ngsl_Timetable extends External_Humanedge_Timetable
{
    use External_Humanedge_APlusDatabaseTrait;


    /**
     * @inheritDoc
     */
    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        // Figure out day number if it's not supplied
        if (!$dayNumber) {
            $dayNumber = $this->getDayNumber($campus);
        }
        if (!$dayNumber) {
            return [];
        }

        // Get the right user
        try {
            $user = \Schoolbox\UserMapper::getInstance()->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $periodJoin = '
            INNER JOIN tblPeriod p ON a.PeriodID = p.ID
            INNER JOIN tblDay d ON a.DayID = d.ID
            LEFT  JOIN tblPeriodTimesByDay pd ON a.PeriodID = pd.PeriodID AND a.DayID = pd.DayID
        ';
        $coreRoomJoin  = "LEFT JOIN tblRoom r ON a.RoomID = r.ID";
        $electRoomJoin = "LEFT JOIN tblRoom r ON b.RoomID = r.ID";
        $teacherSelect = "
            t.Code          AS StaffID,
            t.FamilyName    AS StaffPreferredName,
            t.PreferredName AS StaffSurname,
            ''              AS StaffTitle
        ";

        $db     = $this->loadAPlusDatabase();
        $params = [
            'externalId' => $externalUserId,
            'dayNumber'  => $dayNumber,
        ];
        if ($user->getRole()->isStudent()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $query = "
                SELECT
                    s.Code + '_' + RIGHT(f.Code,1) AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    CAST(ISNULL(pd.StartTime, p.StartTime) AS TIME) as StartTime,
                    CAST(ISNULL(pd.EndTime, p.EndTime) AS TIME) as EndTime,
                    r.Code AS Room,
                    a.NumConseqPeriods AS PeriodSpan,
                    $teacherSelect
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                LEFT JOIN tblTeacher t on a.TeacherID = t.ID
                $periodJoin
                $coreRoomJoin
                INNER JOIN tblStudent st on a.FormID = st.FormID
                INNER JOIN tblForm f ON a.FormId = f.ID
                WHERE st.deleted = 0
                AND st.code = :externalId
                AND d.SortOrder = :dayNumber

                UNION

                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    CAST(ISNULL(pd.StartTime, p.StartTime) AS TIME) as StartTime,
                    CAST(ISNULL(pd.EndTime, p.EndTime) AS TIME) as EndTime,
                    r.Code AS Room,
                    a.NumConseqPeriods AS PeriodSpan,
                    $teacherSelect
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblAllocation a ON b.BlockID = a.BlockID
                LEFT JOIN tblTeacher t on a.TeacherID = t.ID
                $periodJoin
                $electRoomJoin
                INNER JOIN tblSubjectChoice sc ON b.YearBlockItemID = sc.YearBlockItemID
                INNER JOIN tblStudent st ON sc.StudentID = st.ID
                WHERE st.deleted = 0
                AND st.code = :externalId
                AND d.SortOrder = :dayNumber

                ORDER BY DayNumber ASC, PeriodNumber ASC
            ";
        } elseif ($user->getRole()->isStaff()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $query = "
                SELECT
                    s.Code + '_' + RIGHT(f.Code,1) AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    CAST(ISNULL(pd.StartTime, p.StartTime) AS TIME) as StartTime,
                    CAST(ISNULL(pd.EndTime, p.EndTime) AS TIME) as EndTime,
                    r.Code AS Room,
                    a.NumConseqPeriods AS PeriodSpan,
                    $teacherSelect
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                $periodJoin
                $coreRoomJoin
                INNER JOIN tblTeacher t on a.TeacherID = t.ID
                INNER JOIN tblForm f ON a.FormId = f.ID
                WHERE t.deleted = 0
                AND t.code = :externalId
                AND d.SortOrder = :dayNumber

                UNION

                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    CAST(ISNULL(pd.StartTime, p.StartTime) AS TIME) as StartTime,
                    CAST(ISNULL(pd.EndTime, p.EndTime) AS TIME) as EndTime,
                    r.Code AS Room,
                    a.NumConseqPeriods AS PeriodSpan,
                    $teacherSelect
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblTeacher t on b.TeacherID = t.ID
                INNER JOIN tblAllocation a ON b.BlockID = a.BlockID
                $periodJoin
                $electRoomJoin
                WHERE t.deleted = 0
                AND t.code = :externalId
                AND d.SortOrder = :dayNumber

                ORDER BY DayNumber ASC, PeriodNumber ASC
            ";
        } else {
            return [];
        }
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus);

        // Build the timetable
        $timetable = [];
        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            $timetable[$x]['class']          = [];
        }
        foreach ($db->getQueryIterator($query, $params) as $timetabledClass) {
            // Account for classes which extend multiple periods
            $periodSpan = range(
                $timetabledClass['PeriodNumber'],
                $timetabledClass['PeriodNumber'] + $timetabledClass['PeriodSpan'] - 1
            );
            foreach ($timetable as $x => $timetablePeriod) {
                if (in_array($timetablePeriod['periodNumber'], $periodSpan)) {
                    $classCode = $timetabledClass['ClassCode'];
                    if (!isset($timetable[$x]['class'][$classCode])) {
                        // Period start/end isn't always the same as the base class period
                        try {
                            $startTime = new Alaress_Date_Format($timetabledClass['StartTime'], 'H:i:s+');
                            $timetable[$x]['periodStart'] = $startTime->getUsingFormat('H:i');
                        } catch (Exception $e) {
                            // noop
                        }
                        try {
                            $endTime = new Alaress_Date_Format($timetabledClass['EndTime'], 'H:i:s+');
                            $timetable[$x]['periodEnd'] = $endTime->getUsingFormat('H:i');
                        } catch (Exception $e) {
                            // noop
                        }

                        $timetable[$x]['class'][$classCode] = array(
                            'description'    => $timetabledClass['ClassName'],
                            'code'           => $timetabledClass['ClassCode'],
                            //                            'campus'         => $timetabledClass['ClassCampus'],
                            'room'           => $timetabledClass['Room'],
                            'staffID'        => $timetabledClass['StaffID'],
                            'staffFirstname' => $timetabledClass['StaffPreferredName'],
                            'staffSurname'   => $timetabledClass['StaffSurname'],
                            'staffTitle'     => $timetabledClass['StaffTitle'],
                        );
                    }
                }
            }
        }
        return $timetable;
    }

    /**
     * @inheritDoc
     */
    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        try {
            $user = \Schoolbox\UserMapper::getInstance()->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $periodJoin = '
            INNER JOIN tblPeriod p ON a.PeriodID = p.ID
            INNER JOIN tblDay d ON a.DayID = d.ID
        ';
        $coreRoomJoin  = "LEFT JOIN tblRoom r ON a.RoomID = r.ID";
        $electRoomJoin = "LEFT JOIN tblRoom r ON b.RoomID = r.ID";
        $teacherSelect = "
            t.Code          AS StaffID,
            t.FamilyName    AS StaffPreferredName,
            t.PreferredName AS StaffSurname,
            ''              AS StaffTitle
        ";

        $db     = $this->loadAPlusDatabase();
        $params = [
            'externalId' => $externalUserId,
        ];
        if ($user->getRole()->isStudent()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $query = "
                SELECT
                    s.Code + '_' + RIGHT(f.Code,1) AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    r.Code AS Room,
                    a.NumConseqPeriods AS PeriodSpan,
                    $teacherSelect
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                LEFT JOIN tblTeacher t on a.TeacherID = t.ID
                $periodJoin
                $coreRoomJoin
                INNER JOIN tblStudent st on a.FormID = st.FormID
                INNER JOIN tblForm f ON a.FormId = f.ID
                WHERE st.deleted = 0
                AND st.code = :externalId

                UNION

                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    r.Code AS Room,
                    a.NumConseqPeriods AS PeriodSpan,
                    $teacherSelect
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblAllocation a ON b.BlockID = a.BlockID
                LEFT JOIN tblTeacher t on a.TeacherID = t.ID
                $periodJoin
                $electRoomJoin
                INNER JOIN tblSubjectChoice sc ON b.YearBlockItemID = sc.YearBlockItemID
                INNER JOIN tblStudent st ON sc.StudentID = st.ID
                WHERE st.deleted = 0
                AND st.code = :externalId

                ORDER BY DayNumber ASC, PeriodNumber ASC
            ";
        } elseif ($user->getRole()->isStaff()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $query = "
                SELECT
                    s.Code + '_' + RIGHT(f.Code,1) AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    r.Code AS Room,
                    a.NumConseqPeriods AS PeriodSpan,
                    $teacherSelect
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                $periodJoin
                $coreRoomJoin
                INNER JOIN tblTeacher t on a.TeacherID = t.ID
                INNER JOIN tblForm f ON a.FormId = f.ID
                WHERE t.deleted = 0
                AND t.code = :externalId

                UNION

                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    r.Code AS Room,
                    a.NumConseqPeriods AS PeriodSpan,
                    $teacherSelect
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblTeacher t on b.TeacherID = t.ID
                INNER JOIN tblAllocation a ON b.BlockID = a.BlockID
                $periodJoin
                $electRoomJoin
                WHERE t.deleted = 0
                AND t.code = :externalId

                ORDER BY DayNumber ASC, PeriodNumber ASC
            ";
        } else {
            return [];
        }
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus);

        // Build the timetable
        $timetable = [];
        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
            // Fill in each day with blanks
            $timetable[$x]['day']            = array_fill(1, $timetabledef['dayCount'], []);
        }
        foreach ($db->getQueryIterator($query, $params) as $timetabledClass) {
            // Account for classes which extend multiple periods
            $periodSpan = range(
                $timetabledClass['PeriodNumber'],
                $timetabledClass['PeriodNumber'] + $timetabledClass['PeriodSpan'] - 1
            );
            foreach ($timetable as $x => $timetablePeriod) {
                if (in_array($timetablePeriod['periodNumber'], $periodSpan)) {
                    $classCode = $timetabledClass['ClassCode'];
                    $dayNumber = $timetabledClass['DayNumber'];
                    if (!isset($timetable[$x]['day'][$dayNumber][$classCode])) {
                        $timetable[$x]['day'][$dayNumber][$classCode] = array(
                            'description'    => $timetabledClass['ClassName'],
                            'code'           => $timetabledClass['ClassCode'],
                            //                            'campus'         => $timetabledClass['ClassCampus'],
                            'room'           => $timetabledClass['Room'],
                            'staffID'        => $timetabledClass['StaffID'],
                            'staffFirstname' => $timetabledClass['StaffPreferredName'],
                            'staffSurname'   => $timetabledClass['StaffSurname'],
                            'staffTitle'     => $timetabledClass['StaffTitle'],
                        );
                    }
                }
            }
        }
        return $timetable;
    }
}
