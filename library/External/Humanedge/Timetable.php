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
        $periods    = $db->getAll("SELECT * FROM tblPeriod WHERE deleted = 0 ORDER BY SortOrder ASC");
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
            $definition['number'][]   = intval($period['Number']);
            $definition['sequence'][] = 1;
        }
        return $definition;
    }

    /**
     * @inheritDoc
     */
    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        // TODO: Implement getDayNumber() method.
    }

    /**
     * @inheritDoc
     */
    function getDayName($dayNumber)
    {
        // TODO: Implement getDayName() method.
    }

    /**
     * @inheritDoc
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0)
    {
        // TODO: Implement getPeriodNumber() method.
    }

    /**
     * @inheritDoc
     */
    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        // If a student: only allocated classes can be retrieved
        $query = "
            SELECT DISTINCT
                s.Code + ISNULL(b.Suffix, '') AS code,
                d.dayNumber as day,
                a.PeriodID as period,
                pd.StartTime,
                pd.EndTime,
                t.Code as TeacherCode
            FROM tblSubject s
            INNER JOIN tblAllocation a on s.ID = a.SubjectID
            INNER JOIN tblStudent st on a.FormID = st.FormID
            INNER JOIN tblTeacher t on a.TeacherID = t.ID
            LEFT  JOIN tblBlockItem b on s.ID = b.SubjectID
            INNER JOIN tblFixedDayList fd ON a.DayID = fd.DayID
            INNER JOIN tblDayOfWeek d ON fd.DayOfWeekId = d.ID
            INNER JOIN tblPeriodTimesByDay pd ON a.DayID = pd.DayID AND a.PeriodID = pd.PeriodID
            WHERE st.deleted = 0
            AND st.code = {$this->getDatabase()->smartQuote($externalUserId)}
        ";
    }

    /**
     * @inheritDoc
     */
    public function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date)
    {
        // TODO: Implement getTimetableForDate() method.
    }

    /**
     * @inheritDoc
     */
    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        // TODO: Implement getFullTimetable() method.
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
