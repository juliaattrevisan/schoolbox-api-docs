<?php
use Alaress\Database\Iterator\IIterator;

/**
 * External_Schoolpro_Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class External_Schoolpro_Timetable implements External_TimetableInterface
{
    use External_MssqlConnector;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setupDatabase();
    }

    /**
     * Number of days per cycle
     *
     * You could derive this, or just hardcode it
     * (I've only seen data for 4 days)
     *
     * @var int
     */
    protected $cycleLength = 4;

    /**
     * Get a timetable definition
     *
     * @param string $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param int $day
     *
     * @return array|bool
     */
    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0, $date = null)
    {
        $this->setCycleLength($campus->getTimetableDay());

        $statement = $this->getConnector()->prepareStoredProcedure('sbGetTimetableDefinition');
        $statement->bindInputs([
            'pUserName' => 'alaress',
            'pUserPass' => 'alaress',
            'pCampusID' => $campus->getExtTimetableCode(),
        ]);
        $iterator = $statement->getIterator();

        $allDefinitions = [];

        // Only stack it up if this day is chosen

        foreach ($iterator as $currentDefinition) {
            if (!$day || ($day == $currentDefinition['DayNumber'])) {
                $allDefinitions['desc'][] = $currentDefinition['PeriodDescription'];
                $allDefinitions['start'][] = $currentDefinition['TimeFrom'];
                $allDefinitions['end'][] = $currentDefinition['TimeTo'];
                $allDefinitions['number'][] = $currentDefinition['PeriodNumber'];
                $allDefinitions['dayNumber'][] = $currentDefinition['DayNumber'];
                $allDefinitions['sequence'][] = 1;
            }
        }

        // Number of days per cycle used in getFullTimetable()

        $allDefinitions['dayCount'] = $this->getCycleLength();
        return $allDefinitions;
    }

    /**
     * Get a day number
     *
     * @param \Schoolbox\Campus $campus
     * @param int $date
     *
     * @return bool
     */
    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        $this->setCycleLength($campus->getTimetableDay());

        if (!$date) {
            $date = $_SERVER['REQUEST_TIME'];
        }

        /* Convert from timestamp to datetime
         */
        if ($date) {
            $dateObj = new Alaress_Date_Format($date, 'U');
            $dateToUse = $dateObj->getDatabaseDate();

            $statement = $this->getConnector()->prepareStoredProcedure('sbGetDayNumber');
            $statement->bindInputs([
                'pUserName'  => 'alaress',
                'pUserPass'  => 'alaress',
                'pCampusID'  => $campus->getExtTimetableCode(),
                'pDateToUse' => $dateToUse,
            ]);

            $statement->execute();

            if ($row = $statement->fetch()) {
                return $row['DayNumber'];
            }
        }
        return false;
    }

    /**
     * Returns the day name
     *
     * @param $dayNumber
     * @return String
     */
    public function getDayName($dayNumber)
    {
        return 'Day ' . $dayNumber;
    }

    /**
     * Get period number for a particular campus
     *
     * @param int|\Schoolbox\Campus $campus
     * @param int $now
     * @return array
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0, $user = null)
    {
        $statement = $this->getConnector()->prepareStoredProcedure('sbGetPeriodNumber');
        $statement->bindInputs([
            'pUserName' => 'alaress',
            'pUserPass' => 'alaress',
            'pCampusID' => $campus->getExtTimetableCode(),
        ]);

        $statement->execute();

        if ($row = $statement->fetch()) {
            return [
                'PeriodNumber' => $row['Identifier'],
                'PeriodNumberSeq' => 1,
            ];
        }

        return [];
    }

    /**
     * Get staff day timetable
     *
     * @param $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param $dayNumber
     *
     * @return IIterator
     */
    private function getStaffDayTimetable($externalUserId, \Schoolbox\Campus $campus, $dayNumber)
    {
        $statement = $this->getConnector()->prepareStoredProcedure('sbGetStaffDayTimetable');
        $statement->bindInputs([
            'pUserName'  => 'alaress',
            'pUserPass'  => 'alaress',
            'pSchoolNum' => $externalUserId,
            'pCampusID'  => $campus->getExtTimetableCode(),
            'pDayNumber' => $dayNumber,
        ]);

        return $statement->getIterator();
    }

    /**
     * Get staff day timetable
     *
     * @param $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param $dayNumber
     *
     * @return IIterator
     */
    private function getStudentDayTimetable($externalUserId, $campus, $dayNumber)
    {
        $statement = $this->getConnector()->prepareStoredProcedure('sbGetStudentDayTimetable');
        $statement->bindInputs([
            'pUserName'  => 'alaress',
            'pUserPass'  => 'alaress',
            'pSchoolNum' => $externalUserId,
            'pCampusID'  => $campus->getExtTimetableCode(),
            'pDayNumber' => $dayNumber,
        ]);

        return $statement->getIterator();
    }

    /**
     * Get the day timetable for a given person
     *
     * @param $externalUserId
     * @param $userId
     * @param \Schoolbox\Campus $campus
     * @param $dayNumber
     *
     * @return array
     */
    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        if (empty($externalUserId)) {
            return [];
        }
        $groupHandler = new Group();

        $definition = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber);

        if ($groupHandler->isStudent($userId)) {
            $iterator = $this->getStudentDayTimetable($externalUserId, $campus, $dayNumber);
        } else {
            $iterator = $this->getStaffDayTimetable($externalUserId, $campus, $dayNumber);
        }

        /* Rewind function on iterator is broken atm, so this ensures that we can iterate
         * multiple times over the class list
         */
        $classList = iterator_to_array($iterator);

        $timetable = [];

        /* For every period defined in the timetable definition,
         * set up a stub stack which contains all the possible
         * periods for this day. We'll append actual classes to
         * this data structure.
         */
        for ($x = 0; isset($definition['desc'][$x]); $x++) {
            $timetable[$x]['periodName'] = $definition['desc'][$x];
            $timetable[$x]['periodStart'] = substr($definition['start'][$x], 0, 5);
            $timetable[$x]['periodEnd'] = substr($definition['end'][$x], 0, 5);
            $timetable[$x]['periodNumber'] = $definition['number'][$x];
            $timetable[$x]['periodSequence'] = $definition['sequence'][$x];
            $timetable[$x]['class'] = array();
        }
        /* With the result set obtained above, append actual class
         * details to the period definition established in the previous loop
         *
         * Sometimes they can stuff up their db, leaving out periods - e.g.
         * Period 1, Period 2, Period 4, Period 5,6,7,8,9... (skipped Period 3)
         * in timetableDefinition, but will have a Period 3 class specified
         * in getStaffDayTimetable.
         *
         * Doing it in a linear fashion means the loops get stuck trying to
         * assign a Period 3 class to a nonexistent Period 3 definition.
         *
         * In other connectors this is done in a "merge" style operation
         * which requires only one loop, but I did it this way because
         * this kind of stuff is currently not documented well.
         */
        for ($x = 0; $x < count($timetable); $x++) {
            foreach ($classList as $currentClass) {
                /* If this class occurs in this period, append its details
                 */
                if ($timetable[$x]['periodNumber'] == $currentClass['Identifier']) {
                    $timetable[$x]['class'][] = array(
                        'description' => $currentClass['ClassDescription'],
                        'code'        => $currentClass['ClassCode'],
                        'room'        => $currentClass['ClassRoom'],
                        'staffID'     => $externalUserId,
                        'dayNumber'   => $currentClass['DayNumber'],
                    );
                    break;
                }
            }
        }

        return $timetable;
    }

    /**
     * Return a user's timetable for a specific date
     *
     * Depending on the role for $userId (staff or student) this summons the respective
     * method to spawn associated timetable
     *
     *
     * @param $externalUserId
     * @param $userId
     * @param Schoolbox\Campus $campus
     * @param Alaress_Date_Format $date
     * @return mixed
     */
    public function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date)
    {
        return $this->getDayTimetable($externalUserId, $userId, $campus, $this->getDayNumber($campus, $date->getUnixTimeStamp()));
    }


    /**
     * Get a full timetable
     *
     * @param $externalUserId
     * @param $userId
     * @param \Schoolbox\Campus $campus
     *
     * @return array
     */
    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        if (empty($externalUserId)) {
            return array();
        }
        $this->setCycleLength($campus->getTimetableDay());
        $allDayTimetables = array();
        /* Get all the days' timetables first
         */
        for ($i = 1; $i <= $this->getCycleLength(); $i++) {
            $allDayTimetables[] = $this->getDayTimetable($externalUserId, $userId, $campus, $i);
        }
        $timetable = array();
        /* If the $row has values and the definition hasn't already been rendered,
         * subst those vars - otherwise do nothing. Sometimes it can be overwritten
         * with empty values, like on chub where there were days, but no subjects
         * in them, producing and empty $row and thus empty definitions
         */
        for ($period = 0; $period < count($allDayTimetables[0]); $period++) {
            for ($day = 1; $day <= $this->getCycleLength(); $day++) {
                $timetable[$period]['day'][$day] = array();
                if (!empty($allDayTimetables[$day-1][$period]['class'][0])) {
                    $timetable[$period]['day'][$day][] = $allDayTimetables[$day-1][$period]['class'][0];
                }
                /* TODO: Have these use convert(varchar(10), theDate, 120) in stored proc
                 */
                $start = substr($allDayTimetables[0][$period]['periodStart'], 0, 5);
                $end = substr($allDayTimetables[0][$period]['periodEnd'], 0, 5);
                /* Port across some values
                 */
                $timetable[$period]['periodName'] = $allDayTimetables[0][$period]['periodName'];
                $timetable[$period]['periodNumber'] = $allDayTimetables[0][$period]['periodNumber'];
                $timetable[$period]['periodStart'] = $start;
                $timetable[$period]['periodEnd'] = $end;
                $timetable[$period]['periodSequence'] = 1;
            }
        }
        return $timetable;
    }

    /**
     * TODO: No stored proc
     *
     * @param  array|string $roomCodes
     * @return array
     */
    public function getRoomTimetable($roomCodes)
    {
        return array();
    }

    /* Getters/Setters
     */

    /**
     * @param int $cycleLength
     */
    private function setCycleLength($cycleLength)
    {
        $this->cycleLength = $cycleLength;
    }

    /**
     * @return int
     */
    private function getCycleLength()
    {
        return $this->cycleLength;
    }
}
