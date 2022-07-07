<?php

/**
 * Synergetic Scheduler Events related queries
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use Alaress_Date_Format;
use InvalidArgumentException;
use Schoolbox\External\Connector\IExternalEvents;
use Schoolbox\External\Connector\Base;
use Schoolbox\User;

class ExternalEvents extends Base implements IExternalEvents
{
    /**
     * @inheritdoc
     */
    public function getExternalEvents(User $user, $startDate, $endDate)
    {
        $start = new Alaress_Date_Format($startDate, 'U');
        $finish = new Alaress_Date_Format($endDate, 'U');


        // If the External ID is NOT a number, then stop the system from checking the external database.

        if (is_numeric($user->getExternalId()) === false || $user->getExternalId() < 0) {
            throw new InvalidArgumentException("External ID is not a Number");
        }


        if ($user->getRole()->isStaff()) {
            $join  = "";
            $where = " AND ss.StaffID = '{$user->getExternalId()}'";
        } else {
            $join  = " LEFT JOIN dbo.StaffScheduleStudentClasses sssc ON ss.StaffScheduleSeq = sssc.StaffScheduleSeq";
            $where = " AND sssc.ID = '{$user->getExternalId()}'";
        }

        $query = "
            SELECT
              ss.StaffScheduleSeq    AS id,
              ss.StaffID             AS owner,
              ss.Comment             AS comment,
              r.Description          AS room,
              0                      AS type,
              CONVERT(VARCHAR(20),ss.ScheduleDateTimeFrom,120) AS start,
              CONVERT(VARCHAR(20),ss.ScheduleDateTimeTo,120)   AS finish,
              l.Description          AS location,
              t.Description          AS team,
              -- This tries to generate a title for the event in the following order
              -- 1. Use the class description (eg. English) from the join of StaffSchedule.SubjectClassesSeq
              -- 2. Use the class code (eg. ENG34) from the join of StaffSchedule.SubjectClassesSeq
              -- 3. If students were linked via StaffScheduleStudentClasses.StaffScheduleSeq, use the concatenation of all of their class codes/descriptions (eg. ENG34, MAT12) as long as this isn't also blank
              -- 4. Use the team description (eg. Kayaking) from the join of StaffSchedule.TeamCode
              -- 5. Use the first 50 characters of StaffSchedule.Comment if there is anything in it
              -- 6. Fallback to a generic title
              CASE WHEN ISNULL(sc.Description, '') = '' AND ISNULL(sc.ClassCode, '') = '' AND (SELECT COUNT(*) FROM StaffScheduleStudentClasses sssc3 JOIN dbo.SubjectClasses sc3 ON sc3.ClassCode = sssc3.ClassCode AND sc3.FileYear = sssc3.FileYear AND sc3.FileSemester = sssc3.FileSemester AND StaffScheduleSeq = ss.StaffScheduleSeq) > 0 THEN
              -- Where no StaffScheduleSeq was specified, but students were invited and had a valid class code- concatenate the individual students class codes/descriptions instead as the title
              LTRIM(RTRIM(STUFF((
                  SELECT DISTINCT ', ' +
                      CASE WHEN sc2.Description IS NULL THEN sssc.ClassCode ELSE sc2.Description END
                  FROM	StaffScheduleStudentClasses sssc
                  JOIN dbo.SubjectClasses sc2 ON sssc.ClassCode = sc2.ClassCode AND sssc.FileYear = sc2.FileYear AND sssc.FileSemester = sc2.FileSemester
                  WHERE	sssc.StaffScheduleSeq = ss.StaffScheduleSeq
                  ORDER BY ', ' + CASE WHEN sc2.Description IS NULL THEN sssc.ClassCode ELSE sc2.Description END
                  FOR	XML PATH ('')), 1, 1, '')))
              -- If a StaffScheduleSeq was specified and we found a class description, use that
              WHEN ISNULL(sc.Description, '') <> '' THEN sc.Description
              -- Otherwise try just the class code
              WHEN ISNULL(sc.ClassCode, '') <> '' THEN sc.ClassCode
              -- Otherwise try the team name
              WHEN ISNULL(t.Description, '') <> '' THEN t.Description
              -- Otherwise try the first 50 characters of the comment
              WHEN ISNULL(LTRIM(RTRIM(ss.Comment)), '') <> '' THEN LEFT(LTRIM(RTRIM(ss.Comment)), 50)
              -- Otherwise we're out of luck making a meaningful title for this event
              ELSE 'Scheduled Meeting' END
                                    AS title
              FROM dbo.StaffSchedule ss
              LEFT JOIN dbo.luLocation l ON ss.LocationCode = l.Code AND ss.LocationCode IS NOT NULL AND ss.LocationCode <> ''
              LEFT JOIN dbo.luRoom r ON ss.Room = r.Code AND ss.Room IS NOT NULL AND ss.Room <> ''
              LEFT JOIN dbo.luTeam t ON ss.TeamCode = t.Code AND ss.TeamCode IS NOT NULL AND ss.TeamCode <> ''
              {$join}
              LEFT JOIN dbo.SubjectClasses sc ON ss.SubjectClassesSeq = sc.SubjectClassesSeq AND ss.SubjectClassesSeq IS NOT NULL AND ss.SubjectClassesSeq <> ''
              WHERE NOT (ss.ScheduleDateTimeTo < '{$start->getDatabase()}' OR ss.ScheduleDateTimeFrom >= '{$finish->getDatabase()}')
              {$where}
              ORDER BY ss.StaffScheduleSeq ASC";

        return $this->databaseExternal->getQueryIterator($query);
    }
}
