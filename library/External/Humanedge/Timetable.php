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
    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0)
    {
        // TODO: Implement getTimetableDefinition() method.
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
        // TODO: Implement getDayTimetable() method.
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
