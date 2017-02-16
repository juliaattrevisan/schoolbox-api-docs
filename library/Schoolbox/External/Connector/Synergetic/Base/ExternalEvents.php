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
use UnexpectedValueException;
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
     * @return string $endDate
     */
    public function getExternalEvents($user, $startDate, $endDate)
    {

        $start = new Alaress_Date_Format($startDate, 'U');
        $finish = new Alaress_Date_Format($endDate, 'U');

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
            FROM SynergyOne.dbo.StaffSchedule StaffSchedule
              LEFT JOIN SynergyOne.dbo.luLocation ON StaffSchedule.LocationCode = luLocation.Code AND StaffSchedule.LocationCode IS NOT NULL AND StaffSchedule.LocationCode <> ''
              LEFT JOIN SynergyOne.dbo.luTeam ON StaffSchedule.TeamCode = luTeam.Code AND StaffSchedule.TeamCode IS NOT NULL AND StaffSchedule.TeamCode <> ''";
        if (!$user->getRole()->isStaff()) {
            $query .= " LEFT JOIN SynergyOne.dbo.StaffScheduleStudentClasses ON StaffSchedule.StaffScheduleSeq = StaffScheduleStudentClasses.StaffScheduleSeq";
        }
        $query .= " LEFT JOIN SynergyOne.dbo.SubjectClasses ON StaffSchedule.SubjectClassesSeq = SubjectClasses.SubjectClassesSeq AND StaffSchedule.SubjectClassesSeq IS NOT NULL AND StaffSchedule.SubjectClassesSeq <> ''
             WHERE StaffSchedule.ScheduleDateTimeFrom > '{$start->getDatabase()}' AND StaffSchedule.ScheduleDateTimeTo < '{$finish->getDatabase()}'";

        if ($user->getRole()->isStaff()) {
            $query .= " AND StaffSchedule.StaffID = {$user->getExternalId()}";
        }
        //Is this always a student...?
        else {
            $query .= " AND StaffScheduleStudentClasses.ID = {$user->getExternalId()}";
        }
            $query .= " ORDER BY StaffSchedule.StaffScheduleSeq ASC";

        return $this->database->getAll($query);
    }
 }
