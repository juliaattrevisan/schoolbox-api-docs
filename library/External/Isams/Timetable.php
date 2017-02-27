<?php
/**
 * Isams Timetable Class
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 * Uncle iSam?
 *    _..._
 *   |||||||
 *   |||||||
 *  _|*****|_
 * ( '-...-' )
 * {`;-----;`}
 * {(| a  a|)}
 *  \|  _\ |/
 *   \  -  /
 *    '{_}`
 */

use Schoolbox\Campus as CampusModel;
use Schoolbox\CampusMapper;

class External_Isams_Timetable implements External_TimetableInterface
{
    /**
     * @return int
     */
    public function getDefaultDayNumber() {
        return 50;
    }

    /**
     * Retrieve timetable definition
     *
     * @param string $externalUserId User we want the definition for
     * @param CampusModel $campus Campus we want the definition for
     * @param int $day School day we want the definition for
     * @param int $date UNIX timestamp of the date we want to get definition from
     * @return array
     */
    public function getTimetableDefinition($externalUserId, CampusModel $campus, $day = null, $date = null)
    {
        if (empty($day)) {
            $day = intval(Config::get('external_isams_default_day_id'));
        }

        // Get period information from database

        $this->externalUser->getStudent($externalUserId);

        $query =
            "
            SELECT
              PeriodID as PeriodNumber,
              1 AS PeriodNumberSeq,
              PeriodName AS Description,
              DayName as DayNumber,
              StartTime,
              EndTime
            FROM V_TimetableStructure
            WHERE CampusCode = '{$campus->getExtTimetableCode()}' AND DayID = {$day}
            ORDER BY DayID, CAST(StartTime AS TIME)
	    ";

        $periods = $this->getDatabase()->getAll($query);

        $definitionArray = [];

        foreach ($periods as $period) {
            if (!isset($found_periods[$period["PeriodNumber"]])) {
                $definition = [];
                $definition["desc"] = $period["Description"];
                $definition["start"] = $period["StartTime"];
                $definition["end"] = $period["EndTime"];
                $definition["number"] = $period["PeriodNumber"];
                $definition["sequence"] = $period["PeriodNumberSeq"];

                $definitionArray[] = $definition;
            }
        }

        $queryPeriodCount = "
            SELECT      COUNT(*) As PeriodCount 
            FROM        V_TimetableStructure 
            WHERE       DayID = {$day}
        ";

        $periods = $this->getDatabase()->getRow($queryPeriodCount);
        $definitionArray['periodCount'] = $periods['PeriodCount'];

        $queryDayCount = "
           SELECT     COUNT(*) AS DayCount
           FROM       (SELECT DISTINCT DayID from V_TimetableStructure WHERE CampusCode = '{$campus->getExtTimetableCode()}') as d
        ";

        $days = $this->getDatabase()->getRow($queryDayCount);
        $definitionArray['dayCount'] = $days['DayCount'];

        return $definitionArray;
    }
}

