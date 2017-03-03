<?php
/**
 * Synergetic Scheduler Events related queries
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use MSSQL;
use Schoolbox\External\Connector\ExternalEventsInterface;
use \Alaress_Date_Format;

/**
 * Class ExternalEvents
 *
 * @package Schoolbox\External\Connector\Synergetic\Base
 */
class ExternalEvents implements ExternalEventsInterface
{
    /**
     * @var MSSQL
     */
    private $database;


    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
    }


    /**
     * Getting events for a user (a teacher or a student) for a specific time interval
     *
     * @param  object $user
     * @param  string $startDate
     * @param  string $endDate
     * @return array|bool
     */
    public function getExternalEvents($user, $startDate, $endDate)
    {

        $start = new Alaress_Date_Format($startDate, 'U');
        $finish = new Alaress_Date_Format($endDate, 'U');

        if ($user->getRole()->isStaff()) {
            $join  = "";
            $where = " AND StaffSchedule.StaffID = '{$user->getExternalId()}'";
        } else {
            $join  = " LEFT JOIN dbo.StaffScheduleStudentClasses ON StaffSchedule.StaffScheduleSeq = StaffScheduleStudentClasses.StaffScheduleSeq";
            $where = " AND StaffScheduleStudentClasses.ID = '{$user->getExternalId()}'";
        }

        $query = "
            SELECT
              StaffSchedule.StaffScheduleSeq    AS id,
              StaffSchedule.StaffID             AS owner,
              StaffSchedule.Comment             AS comment,
              StaffSchedule.Room                AS room,
              CONVERT(VARCHAR(20),StaffSchedule.ScheduleDateTimeFrom,120) AS start,
              CONVERT(VARCHAR(20),StaffSchedule.ScheduleDateTimeTo,120)   AS finish,
              luLocation.Description            AS location,
              luTeam.Description                AS team,
              SubjectClasses.Description        AS title
            FROM dbo.StaffSchedule StaffSchedule
            LEFT JOIN dbo.luLocation ON StaffSchedule.LocationCode = luLocation.Code AND StaffSchedule.LocationCode IS NOT NULL AND StaffSchedule.LocationCode <> ''
            LEFT JOIN dbo.luTeam ON StaffSchedule.TeamCode = luTeam.Code AND StaffSchedule.TeamCode IS NOT NULL AND StaffSchedule.TeamCode <> ''
            {$join}
            LEFT JOIN dbo.SubjectClasses ON StaffSchedule.SubjectClassesSeq = SubjectClasses.SubjectClassesSeq AND StaffSchedule.SubjectClassesSeq IS NOT NULL AND StaffSchedule.SubjectClassesSeq <> ''
            WHERE NOT (StaffSchedule.ScheduleDateTimeFrom < '{$start->getDatabase()}' OR StaffSchedule.ScheduleDateTimeTo >= '{$finish->getDatabase()}')
            {$where}
            ORDER BY StaffSchedule.StaffScheduleSeq ASC";
        
        return $this->database->getAll($query);
    }
 }
