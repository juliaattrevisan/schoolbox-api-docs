<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * HumanEdge implementation of timetable.
 */
class External_Humanedge_Timetable implements External_TimetableInterface
{
    use External_Humanedge_APlusDatabaseTrait;

    /**
     * @var MSSQL $database
     */
    private $database;

//    /**
//     * Constructor
//     */
//    public function __construct()
//    {
//        $this->setDatabase(
//            new MSSQL(
//                Config::get('external_humanedge_aplus_db_db'),
//                [
//                    'host'     => Config::get('external_humanedge_aplus_db_host'),
//                    'user'     => Config::get('external_humanedge_aplus_db_user'),
//                    'passwd'   => Config::get('external_humanedge_aplus_db_passwd'),
//                    'db'       => Config::get('external_humanedge_aplus_db_db'),
//                    'encoding' => Config::get('external_db_encoding'),
//                ],
//                true
//            )
//        );
////        $this->externalUser = new External_User('humanedge');
//    }

    /**
     * @inheritDoc
     */
    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0)
    {
        $db         = $this->loadAPlusDatabase();
        $dayCount   = $db->getOne("SELECT COUNT(*) FROM tblDay WHERE deleted = 0");
        $periods    = $db->getAll("
            SELECT
                Name,
                CAST(StartTime AS TIME) AS StartTime,
                CAST(EndTime AS TIME) AS EndTime,
                SortOrder
            FROM tblPeriod
            WHERE deleted = 0
            ORDER BY SortOrder ASC
        ");
        $definition = [
            'desc'        => [],
            'start'       => [],
            'end'         => [],
            'number'      => [],
            'sequence'    => [],
            'periodCount' => count($periods),
            'dayCount'    => $dayCount,
        ];
        foreach ($periods as $period) {
            $definition['desc'][]     = $period['Name'];
            $definition['start'][]    = $period['StartTime'];
            $definition['end'][]      = $period['EndTime'];
            $definition['number'][]   = intval($period['SortOrder']);
            $definition['sequence'][] = 1;
        }
        return $definition;
    }

    /**
     * @inheritDoc
     */
    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        $db   = $this->loadAPlusDatabase();
        $time = $date
            ? new Alaress_Date_Format($date, Alaress_Date_Format::TIMESTAMP)
            : Alaress_Date_Format::now()
        ;
        return $db->getOne("
            SELECT d.[SortOrder]
            FROM tblDay d
            INNER JOIN tblCalendarDay c ON d.ID = c.TimetableDayID
            WHERE CAST(c.TimetableDate AS DATE) = CAST({$db->smartQuote($time->getDatabase())} AS DATE)
            AND d.deleted = 0
        ");
    }

    /**
     * @inheritDoc
     */
    function getDayName($dayNumber)
    {
        $db = $this->loadAPlusDatabase();
        return $db->getOne("SELECT [Name] FROM tblDay WHERE SortOrder = {$db->smartQuote($dayNumber)} AND deleted = 0");
    }

    /**
     * @inheritDoc
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0)
    {
        $db   = $this->loadAPlusDatabase();
        $time = $now
            ? new Alaress_Date_Format($now, Alaress_Date_Format::TIMESTAMP)
            : Alaress_Date_Format::now()
        ;
        return $db->getOne("
            SELECT [SortOrder]
            FROM tblPeriod
            WHERE CAST({$db->smartQuote($time->getDatabase())} AS TIME)
                BETWEEN CAST(StartTime AS TIME) AND CAST(EndTime AS TIME)
            AND deleted = 0
        ");
    }

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
            LEFT  JOIN tblRoom r ON a.RoomID = r.ID
        ';

        $db        = $this->loadAPlusDatabase();
        $quotedId  = $db->smartQuote($externalUserId);
        $quotedDay = $db->smartQuote($dayNumber);
        if ($user->getRole()->isStudent()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $query = "
                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    CAST(ISNULL(pd.StartTime, p.StartTime) AS TIME) as StartTime,
                    CAST(ISNULL(pd.EndTime, p.EndTime) AS TIME) as EndTime,
                    r.Code AS Room
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                $periodJoin
                INNER JOIN tblStudent st on a.FormID = st.FormID
                LEFT JOIN tblBlockItem b on s.ID = b.SubjectID
                WHERE st.deleted = 0
                AND st.code = $quotedId
                AND d.SortOrder = $quotedDay

                UNION ALL

                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    CAST(ISNULL(pd.StartTime, p.StartTime) AS TIME) as StartTime,
                    CAST(ISNULL(pd.EndTime, p.EndTime) AS TIME) as EndTime,
                    r.Code AS Room
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblAllocation a ON b.BlockID = a.BlockID
                $periodJoin
                INNER JOIN tblSubjectChoice sc ON b.YearBlockItemID = sc.YearBlockItemID
                INNER JOIN tblStudent st ON sc.StudentID = st.ID
                WHERE st.deleted = 0
                AND st.code = $quotedId
                AND d.SortOrder = $quotedDay

                ORDER BY DayNumber ASC, PeriodNumber ASC
            ";
        } elseif ($user->getRole()->isStaff()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $query = "
                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    CAST(ISNULL(pd.StartTime, p.StartTime) AS TIME) as StartTime,
                    CAST(ISNULL(pd.EndTime, p.EndTime) AS TIME) as EndTime,
                    r.Code AS Room
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                $periodJoin
                INNER JOIN tblTeacher t on a.TeacherID = t.ID
                LEFT JOIN tblBlockItem b on s.ID = b.SubjectID
                WHERE t.deleted = 0
                AND t.code = $quotedId
                AND d.SortOrder = $quotedDay

                UNION ALL

                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    CAST(ISNULL(pd.StartTime, p.StartTime) AS TIME) as StartTime,
                    CAST(ISNULL(pd.EndTime, p.EndTime) AS TIME) as EndTime,
                    r.Code AS Room
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblTeacher t on b.TeacherID = t.ID
                INNER JOIN tblAllocation a ON b.BlockID = a.BlockID
                $periodJoin
                WHERE t.deleted = 0
                AND t.code = $quotedId
                AND d.SortOrder = $quotedDay

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
        foreach ($db->getAll($query) as $timetabledClass) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($timetabledClass['PeriodNumber'] == $timetablePeriod['periodNumber']) {
                    $classCode = $timetabledClass['ClassCode'];
                    if (!isset($timetable[$x]['class'][$classCode])) {
                        // Period start/end isn't always the same as the base class period
                        $timetable[$x]['periodStart']       = $timetabledClass['StartTime'];
                        $timetable[$x]['periodEnd']         = $timetabledClass['EndTime'];
                        $timetable[$x]['class'][$classCode] = array(
                            'description'    => $timetabledClass['ClassName'],
                            'code'           => $timetabledClass['ClassCode'],
//                            'campus'         => $timetabledClass['ClassCampus'],
                            'room'           => $timetabledClass['Room'],
//                            'staffID'        => $timetabledClass['StaffID'],
//                            'staffFirstname' => $timetabledClass['StaffPreferredName'],
//                            'staffSurname'   => $timetabledClass['StaffSurname'],
//                            'staffTitle'     => $timetabledClass['StaffTitle'],
                        );
                        break;
                    }
                }
            }
        }
        return $timetable;
    }

    /**
     * @inheritDoc
     */
    public function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date)
    {
        return $this->getDayTimetable(
            $externalUserId,
            $userId,
            $campus,
            $this->getDayNumber($campus, $date->getUnixTimeStamp())
        );
    }

    /**
     * @inheritDoc
     */
    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        // Statement 1: core subjects
        // Statement 2: electives

        try {
            $user = \Schoolbox\UserMapper::getInstance()->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $periodJoin = '
            INNER JOIN tblPeriod p ON a.PeriodID = p.ID
            INNER JOIN tblDay d ON a.DayID = d.ID
            LEFT  JOIN tblPeriodTimesByDay pd ON a.PeriodID = pd.PeriodID AND a.DayID = pd.DayID
            LEFT  JOIN tblRoom r ON a.RoomID = r.ID
        ';

        $db = $this->loadAPlusDatabase();
        $quotedId = $db->smartQuote($externalUserId);
        if ($user->getRole()->isStudent()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $query = "
                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    r.Code AS Room
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                $periodJoin
                INNER JOIN tblStudent st on a.FormID = st.FormID
                LEFT JOIN tblBlockItem b on s.ID = b.SubjectID
                WHERE st.deleted = 0
                AND st.code = $quotedId

                UNION ALL

                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    r.Code AS Room
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblAllocation a ON b.BlockID = a.BlockID
                $periodJoin
                INNER JOIN tblSubjectChoice sc ON b.YearBlockItemID = sc.YearBlockItemID
                INNER JOIN tblStudent st ON sc.StudentID = st.ID
                WHERE st.deleted = 0
                AND st.code = $quotedId

                ORDER BY DayNumber ASC, PeriodNumber ASC
            ";
        } elseif ($user->getRole()->isStaff()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $query = "
                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    r.Code AS Room
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                $periodJoin
                INNER JOIN tblTeacher t on a.TeacherID = t.ID
                LEFT JOIN tblBlockItem b on s.ID = b.SubjectID
                WHERE t.deleted = 0
                AND t.code = $quotedId

                UNION ALL

                SELECT
                    s.Code + ISNULL(b.Suffix, '') AS ClassCode,
                    s.Name AS ClassName,
                    d.SortOrder as DayNumber,
                    p.SortOrder as PeriodNumber,
                    r.Code AS Room
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblTeacher t on b.TeacherID = t.ID
                INNER JOIN tblAllocation a ON b.BlockID = a.BlockID
                $periodJoin
                WHERE t.deleted = 0
                AND t.code = $quotedId

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
        foreach ($db->getAll($query) as $timetabledClass) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($timetabledClass['PeriodNumber'] == $timetablePeriod['periodNumber']) {
                    $classCode = $timetabledClass['ClassCode'];
                    $dayNumber = $timetabledClass['DayNumber'];
                    if (!isset($timetable[$x]['day'][$dayNumber][$classCode])) {
                        $timetable[$x]['day'][$dayNumber][$classCode] = array(
                            'description'    => $timetabledClass['ClassName'],
                            'code'           => $timetabledClass['ClassCode'],
//                            'campus'         => $timetabledClass['ClassCampus'],
                            'room'           => $timetabledClass['Room'],
//                            'staffID'        => $timetabledClass['StaffID'],
//                            'staffFirstname' => $timetabledClass['StaffPreferredName'],
//                            'staffSurname'   => $timetabledClass['StaffSurname'],
//                            'staffTitle'     => $timetabledClass['StaffTitle'],
                        );
                        break;
                    }
                }
            }
        }
        return $timetable;
    }

    /**
     * @inheritDoc
     */
    public function getRoomTimetable($roomCodes)
    {
        // TODO: Implement getRoomTimetable() method.
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
