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
                  LEFT JOIN SynergyOne.dbo.luLocation ON luLocation.Code = StaffSchedule.LocationCode
                  LEFT JOIN SynergyOne.dbo.luTeam ON luTeam.Code = StaffSchedule.TeamCode";
            if (!$user->getRole()->isStaff()) {
                $query .= " LEFT JOIN SynergyOne.dbo.StaffScheduleStudentClasses ON StaffSchedule.StaffScheduleSeq = StaffScheduleStudentClasses.StaffScheduleSeq";
            }
            $query .= " LEFT JOIN SynergyOne.dbo.SubjectClasses ON StaffSchedule.SubjectClassesSeq = SubjectClasses.SubjectClassesSeq
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