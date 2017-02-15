<?php
/**
 * Synergetic Community Portal related queries
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 *
 * @specialThanksTo Damian Sloane <damian.sloane@alaress.com.au>
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use Alaress\DebugBar\Instance as DebugBar;
use MSSQL;
use Schoolbox\External\Connector\ExternalEventsInterface;
use UnexpectedValueException;
use \Alaress_Date_Format;

class ExternalEvents implements ExternalEventsInterface
{
    /**
     * @var MSSQL
     */
    private $database;


    /**
     * Constructor
     *
     * @param string $key
     * @param string $initializationVector
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
        // Figure out the current year and semester
        $externalUser = new \External_User();
    }


    /**
     * Updates the community portal password for this user.
     *
     * @param  string $communityId
     * @param  string $newPassword
     * @return bool
     * @throws UnexpectedValueException if either key or IV was not set
     */
    public function getExternalEvents($user, $startDate, $endDate)
    {

        $start = new Alaress_Date_Format($startDate, 'U');
        $finish = new Alaress_Date_Format($endDate, 'U');

        if ($user->getRole()->isStaff()) {
            $query = "
            SELECT
              StaffSchedule.StaffID             AS owner,
              StaffSchedule.Comment             AS comment,
              StaffSchedule.Room                AS room,
              CONVERT(VARCHAR(20),StaffSchedule.ScheduleDateTimeFrom,120) AS start,
              CONVERT(VARCHAR(20),StaffSchedule.ScheduleDateTimeTo,120)   AS finish,
              luLocation.Description            AS location,
              luTeam.Description                AS team,
            FROM SynergyOne.dbo.StaffSchedule StaffSchedule
              LEFT JOIN SynergyOne.dbo.luLocation ON luLocation.Code = StaffSchedule.LocationCode
              LEFT JOIN SynergyOne.dbo.luTeam ON luTeam.Code = StaffSchedule.TeamCode
            WHERE StaffSchedule.ScheduleDateTimeFrom > '{$start->getDatabase()}' AND StaffSchedule.ScheduleDateTimeTo < '{$finish->getDatabase()}'
            AND StaffSchedule.StaffID = {$user->getExternalId()} 
            ORDER BY StaffSchedule.StaffScheduleSeq ASC
                    ";
        }
        else if ($user->getRole()->isStudent()) {
            $query = "
            SELECT
              StaffSchedule.StaffID             AS owner,
              StaffSchedule.Comment             AS description,
              StaffSchedule.Room                AS room,
              CONVERT(VARCHAR(20),StaffSchedule.ScheduleDateTimeFrom,120) AS start,
              CONVERT(VARCHAR(20),StaffSchedule.ScheduleDateTimeTo,120)   AS finish,
              luLocation.Description            AS location,
              luTeam.Description                AS title,
              StaffScheduleStudentClasses
            FROM SynergyOne.dbo.StaffSchedule StaffSchedule
              LEFT JOIN SynergyOne.dbo.luLocation ON luLocation.Code = StaffSchedule.LocationCode
              LEFT JOIN SynergyOne.dbo.luTeam ON luTeam.Code = StaffSchedule.TeamCode
              LEFT JOIN SynergyOne.dbo.StaffScheduleStudentClasses ON StaffScheduleStudentClasses.StaffScheduleSeq = StaffSchedule.StaffScheduleSeq
            WHERE StaffSchedule.ScheduleDateTimeFrom > '{$start->getDatabase()}' AND StaffSchedule.ScheduleDateTimeTo < '{$finish->getDatabase()}'
            AND (StaffSchedule.StaffID = {$user->getExternalId()} OR StaffScheduleStudentClasses.ID = {$user->getExternalId()})
            ORDER BY StaffSchedule.StaffScheduleSeq ASC
                    ";
        }
        return $this->database->getAll($query);
    }
 }