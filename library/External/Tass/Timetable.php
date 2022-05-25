<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Campus;
use Alaress\Database\Predicate\Builder as PredicateBuilder;

/**
 * Class External_Tass_Timetable
 *
 * This file provides access to TASS timetable info
 */
class External_Tass_Timetable implements External_TimetableInterface
{
    use External_MssqlConnector;


    /**
     * Keeps track of calendar day events
     */
    private $tassDay;


    /**
     * @var Group
     */
    private $groupHandler;


    /**
     * @var External_UserInterface
     */
    private $externalUser;


    /**
     * External_Tass_Timetable constructor.
     */
    public function __construct()
    {
        $this->setupDatabase();
        $this->groupHandler = new Group();
        $this->externalUser = new External_User('tass');
    }


    /**
     * Get campus code from campus external timetable code
     * @param Campus $campus
     * @return mixed|string
     */
    private function getCampusCode(Campus $campus)
    {
        if ($campus->getExtTimetableCode() === null || !strpos($campus->getExtTimetableCode(), '-')) {
            return '';
        }
        [$campusCode, $timetableCode] = explode('-', $campus->getExtTimetableCode());
        return $campusCode;
    }

    /**
     * Get timetable code from campus external timetable code
     * @param Campus $campus
     * @return mixed|string
     */
    private function getTimetableCode(Campus $campus)
    {
        if ($campus->getExtTimetableCode() === null || !strpos($campus->getExtTimetableCode(), '-')) {
            return '';
        }
        [$campusCode, $timetableCode] = explode('-', $campus->getExtTimetableCode());
        return $timetableCode;
    }

    private function getDays(Campus $campus)
    {
        return $campus->getTimetableDay();
    }

    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {

        $campusCode    = $this->getCampusCode($campus);
        $timetableCode = $this->getTimetableCode($campus);

        /* Fetch the longest day to use as our day template...  */
        if ($day == 0) {
            $query      = "SELECT tt_id, day_code, prd_num FROM ttdays WHERE tt_id = '" . $timetableCode . "' AND cmpy_code = '$campusCode' ORDER BY prd_num DESC";
            $longestDay = $this->getConnector()->executeQuery($query)->fetch();
            $day        = $longestDay['day_code'];
        }

        $query = "SELECT prd_desc, prd_start, prd_end, prd_code, day_code FROM ttperiod
                WHERE tt_id = '" . $timetableCode . "' AND cmpy_code = '$campusCode' AND day_code='$day'
                ORDER BY prd_code";

        $statement = $this->getConnector()->executeQuery($query);
        $periodCount = 0;
        while ($period = $statement->fetch()) {
            $timetableDef['desc'][]   = $period['prd_desc'];
            $timetableDef['start'][]  = date("g:ia", strtotime($period['prd_start']));
            $timetableDef['end'][]    = date("g:ia", strtotime($period['prd_end']));
            $timetableDef['number'][] = $period['prd_code'];
            $periodCount++;
        }

        $timetableDef['periodCount'] = $longestDay['prd_num'];
        $timetableDef['dayCount']    = $this->getDays($campus);
        return $timetableDef;
    }


    /**
     * Retrieve day number from TASS database
     *
     * @param Campus $campus
     * @param int    $date
     *
     * @return int|string
     * @throws \Alaress\Database\Exception
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        if (!$date) {
            $date = time();
        }

        $params = [
            'cmpyCode' => $this->getCampusCode($campus),
            'ttDate'   => date("Y-m-d 00:00:00", $date),
            'ttId'     => $this->getTimetableCode($campus),
        ];

        $query = '
            SELECT  day_code
            FROM    ttcalendar
            WHERE   cmpy_code = :cmpyCode AND
                    ttdate = :ttDate AND
                    tt_id = :ttId
        ';

        $result = $this->getConnector()->executeQuery($query, $params)->fetchColumn();

        if (strlen(trim($result)) === 0) {
            return 0;
        }

        return $result;
    }


    /**
     * Returns the day name
     *
     * @param int $dayNumber
     * @return String
     */
    public function getDayName($dayNumber): string
    {
        return 'Day ' . $dayNumber;
    }


    public function getLastDay($date, Campus $campus)
    {

        $nowmssqldate = date('Y-m-d', $date) . ' 00:00:00';

        $query = "SELECT ttdate, day_code FROM ttcalendar
                    WHERE ttdate <= '$nowmssqldate'
                    AND cmpy_code = '" . $this->getCampusCode($campus) . "'
                    AND tt_id = '" . $this->getTimetableCode($campus) . "'
                    ORDER BY ttdate DESC";

        $result               = $this->getConnector()->executeQuery($query)->fetch();
        $time                 = strtotime($result['ttdate']);
        $this->tassDay[$time] = $result['day_code'];

        return $time;
    }


    /**
     * @inheritDoc
     */
    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array
    {
        return [];
    }

    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, ?Alaress_Date_Format $date = null): array
    {
        if (empty($externalUserId)) {
            return array();
        }
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber);

        $params = [
            'fileSemester'  => $this->externalUser->getCurrentSemester($externalUserId),
            'fileYear'      => $this->externalUser->getCurrentYear($externalUserId),
            'externalId'    => $externalUserId,
            'dayNumber'     => $dayNumber,
            'cmpyCode'      => $this->getCampusCode($campus),
            'timetableCode' => $this->getTimetableCode($campus),
        ];

        if ($this->groupHandler->isStudent($userId)) {
            $query = 'SELECT room_code, tch_code, ttmast.sub_code,ISNULL(ttmast.year_grp,subtab.year_grp) as year_grp, ttmast.class, prd_code, sub_desc
                        FROM studsub
                        JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
                        JOIN ttmast ON studsub.sub_code=ttmast.sub_code AND studsub.cmpy_code=ttmast.cmpy_code AND studsub.semester=ttmast.semester
                                AND studsub.class=ttmast.class AND (student.year_grp=ttmast.year_grp OR ttmast.year_grp IS null) AND studsub.yr_study=ttmast.year_num
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE yr_study = :fileYear AND studsub.semester = :fileSemester
                                AND ttmast.cmpy_code = :cmpyCode
                                AND ttmast.tt_id = :timetableCode
                                AND studsub.stud_code = :externalId
                                AND day_code = :dayNumber
                                AND (studsub.from_date <= CAST(GETDATE() AS DATE) OR studsub.from_date IS NULL)
                                AND (studsub.to_date >= CAST(GETDATE() AS DATE) OR studsub.to_date IS NULL)
                        ORDER BY prd_code';
        } else {
            $query = 'SELECT room_code, tch_code, ttmast.sub_code, ISNULL(ttmast.year_grp,subtab.year_grp) as year_grp, class, prd_code, sub_desc
                        FROM ttmast
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE year_num = :fileYear AND semester = :fileSemester
                            AND ttmast.cmpy_code = :cmpyCode
                            AND ttmast.tt_id = :timetableCode
                            AND tch_code = :externalId
                            AND day_code = :dayNumber
                         ORDER BY prd_code';
        }

        $statement = $this->getConnector()->executeQuery($query, $params);

        $timetable = array();
        $useYearInCode = $this->config->get('external_tass_year_in_class_code');

        // Build the timetable
        if (is_array($timetabledef['desc'])) {
            foreach ($timetabledef['desc'] as $x => $name) {
                $timetable[$x]['periodName'] = $timetabledef['desc'][$x];
                $timetable[$x]['periodStart'] = $timetabledef['start'][$x];
                $timetable[$x]['periodEnd'] = $timetabledef['end'][$x];
                $timetable[$x]['periodNumber'] = $timetabledef['number'][$x];
                $timetable[$x]['periodSequence'] = 1;
                $timetable[$x]['class'] = array();
            }

            // Add classes into it
            while ($desc = $statement->fetch()) {
                if (!$useYearInCode) {
                    $desc['year_grp'] = '';
                }
                $code = trim($desc['year_grp']) . '-' . trim($desc['sub_code']) . '-' . trim($desc['class']);
                foreach ($timetable as $x => $timetablePeriod) {
                    if (
                        $desc['prd_code'] == $timetablePeriod['periodNumber']
                        && $timetablePeriod['periodSequence'] != 2
                        && !isset($timetable[$x]['class'][$code])
                    ) {
                        $timetable[$x]['class'][$code] = array(
                            'description' => $desc['sub_desc'],
                            'code'        => $code,
                            'campus'      => $campus->getExtTimetableCode(),
                            'room'        => $desc['room_code'],
                            'staffID'     => $desc['tch_code'],
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
    public function getTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
    {
        return $this->getDayTimetable($externalUserId, $userId, $campus, $this->getDayNumber($campus, $date->getUnixTimeStamp()));
    }


    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
    {
        if (empty($externalUserId)) {
            return array();
        }
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus);

        $params = [
            'fileSemester' => $this->externalUser->getCurrentSemester($externalUserId),
            'fileYear'     => $this->externalUser->getCurrentYear($externalUserId),
            'externalId'   => $externalUserId,
            'cmpyCode'      => $this->getCampusCode($campus),
            'timetableCode' => $this->getTimetableCode($campus),
        ];

        if ($this->groupHandler->isStudent($userId)) {
            $query = 'SELECT room_code, tch_code, ttmast.sub_code,  ISNULL(ttmast.year_grp,subtab.year_grp) as year_grp, ttmast.class, prd_code, sub_desc, day_code
                            FROM studsub
                            JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
                            JOIN ttmast ON studsub.sub_code=ttmast.sub_code AND studsub.cmpy_code=ttmast.cmpy_code AND studsub.semester=ttmast.semester
                                    AND studsub.class=ttmast.class AND (student.year_grp=ttmast.year_grp OR ttmast.year_grp IS NULL) AND studsub.yr_study=ttmast.year_num
                            JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                            WHERE yr_study = :fileYear AND studsub.semester = :fileSemester
                                    AND ttmast.cmpy_code = :cmpyCode
                                    AND ttmast.tt_id = :timetableCode
                                    AND studsub.stud_code = :externalId
                                    AND (studsub.from_date <= CAST(GETDATE() AS DATE) OR studsub.from_date IS NULL)
                                    AND (studsub.to_date >= CAST(GETDATE() AS DATE) OR studsub.to_date IS NULL)
                            ORDER BY prd_code, day_code';
        } else {
            $query = 'SELECT room_code, tch_code, day_code, ttmast.sub_code, ISNULL(ttmast.year_grp,subtab.year_grp) as year_grp, class, prd_code, sub_desc
                        FROM ttmast
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE year_num = :fileYear AND semester = :fileSemester
                            AND ttmast.cmpy_code = :cmpyCode
                            AND ttmast.tt_id = :timetableCode
                            AND tch_code = :externalId
                         ORDER BY prd_code, day_code';
        }

        $dayCount  = $timetabledef['dayCount'];
        $statement    = $this->getConnector()->executeQuery($query, $params);
        $timetable = array();
        $useYearInCode = $this->config->get('external_tass_year_in_class_code');

        // Build the timetable
        // TODO do this somewhere generic as it's the same for all connectors
        if (is_array($timetabledef['desc'])) {
            foreach ($timetabledef['desc'] as $x => $name) {
                $timetable[$x]['periodName'] = $timetabledef['desc'][$x];
                $timetable[$x]['periodStart'] = $timetabledef['start'][$x];
                $timetable[$x]['periodEnd'] = $timetabledef['end'][$x];
                $timetable[$x]['periodNumber'] = $timetabledef['number'][$x];
                $timetable[$x]['periodSequence'] = 1;
                $timetable[$x]['day'] = array_fill(1, $dayCount, array());
            }

            // Build the class list
            while ($desc = $statement->fetch()) {
                if (!$useYearInCode) {
                    $desc['year_grp'] = '';
                }
                $code = trim($desc['year_grp']) . '-' . trim($desc['sub_code']) . '-' . trim($desc['class']);
                foreach ($timetable as $x => $timetablePeriod) {
                    if (
                        $desc['prd_code'] == $timetablePeriod['periodNumber']
                        && $timetablePeriod['periodSequence'] != 2
                        && !isset($timetable[$x]['day'][$desc['day_code']][$code])
                    ) {
                        $timetable[$x]['day'][$desc['day_code']][$code] = array(
                            'description' => $desc['sub_desc'],
                            'code'        => $code,
                            'campus'      => $campus->getExtTimetableCode(),
                            'room'        => $desc['room_code'],
                            'staffID'     => $desc['tch_code'],
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
    public function getRoomTimetable(array $roomCodes): array
    {
        if (empty($roomCodes)) {
            return [];
        }
        // numeric room codes causes conversion error
        $roomCodes = array_map('strval', $roomCodes);
        $predicate = PredicateBuilder::getArrayBindings($roomCodes, 'roomCodes');
        $query = "
            SELECT
                RTRIM(room_code) AS RoomCode,
                subtab.sub_desc AS Description,
                ttmast.day_code AS DayNumber,
                RTRIM(CAST(ttmast.cmpy_code AS VARCHAR(20)))
                    + '-'
                    + RTRIM(CAST(ttmast.tt_id AS VARCHAR(20))) AS TimetableGroup,
                ttmast.cmpy_code AS ClassCampus,
                RTRIM(CAST(ISNULL(ttmast.year_grp,subtab.year_grp) AS VARCHAR(20)))
                    + '-'
                    + RTRIM(CAST(ttmast.sub_code AS VARCHAR(20)))
                    + '-'
                    + RTRIM(CAST(ttmast.class AS VARCHAR(20)))
                    AS ClassCode,
                ttmast.tch_code AS StaffID,
                '' AS StaffTitle,
                '' AS StaffPreferredName,
                teacher.tch_name AS StaffSurname,
                CONVERT(VARCHAR(8), ttperiod.prd_start, 108) AS PeriodStart,
                CONVERT(VARCHAR(8), ttperiod.prd_end, 108) AS PeriodEnd,
                ttperiod.prd_desc AS PeriodName
            FROM ttmast
            LEFT JOIN subtab ON
                subtab.cmpy_code=ttmast.cmpy_code AND
                subtab.sub_code=ttmast.sub_code
            LEFT JOIN teacher ON
                teacher.cmpy_code=ttmast.cmpy_code AND
                teacher.tch_code=ttmast.tch_code
            LEFT JOIN ttperiod ON
                ttperiod.cmpy_code=ttmast.cmpy_code AND
                ttperiod.day_code=ttmast.day_code AND
                ttperiod.prd_code=ttmast.prd_code AND
                ttperiod.tt_id=ttmast.tt_id
            WHERE year_num  = :year
            AND   semester  = :semester
            AND   room_code IN ({$predicate['placeholders']})
            ORDER BY
                ttmast.day_code ASC,
                ttmast.prd_code ASC
        ";
        $params =
            [
                'semester' => $this->externalUser->getCurrentSemester(),
                'year'     => $this->externalUser->getCurrentYear(),
            ];
        $params = array_merge($params, $predicate['bindings']);

        return $this->getConnector()->executeQuery($query, $params)->fetchAll();
    }


    /**
     * @inheritDoc
     */
    public function findCycleLookup(): array
    {
        return [];
    }
}
