<?php
/**
 * External_Denbigh2_Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Denbigh2_Timetable implements External_TimetableInterface {

    /**
     * An instance of Group
     *
     * @var Group
     */
    private $groupHandler = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setGroupHandler(new Group());
    }

    /**
     * The headings of the timetable, going horizontally
     * from left to right containing all the times and day numbers
     *
     * Has the structure for each period
     *
     * $timetableDef['desc'][]
     * $timetableDef['start'][]
     * $timetableDef['end'][]
     * $timetableDef['number'][]
     * $timetableDef['sequence'][]
     *
     * @param string $externalUserId
     * @param $campus
     * @param int $day
     * @return array
     */
    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0)
    {
        $maxPeriodsPerDay = 6;
        $maxDays = $campus->getTimetableDay();

        $timetableDef = array();

        $api = new External_Denbigh2_Api('PeriodTimes', array());

        if (!$api->hasErrors()) {
            $allPeriods = $api->getResult()->data;

            /* Day-specific e.g. for Dashboard
             */
            $i = 0;

            if (count($allPeriods)) {
                foreach ($allPeriods as $currentPeriod) {
                    if ($i < $maxPeriodsPerDay) {
                        if (!$day || $currentPeriod->{'Day of Cycle'} == $day) {

                            /* Format some times
                             */
                            $start = new \Alaress_Date_Format($currentPeriod->{'Period Start Time'}, 'H:i:s');
                            $end = new \Alaress_Date_Format($currentPeriod->{'Period End Time'}, 'H:i:s');

                            $timetableDef['desc'][$i] = "Period {$currentPeriod->{'Period'}}";
                            $timetableDef['start'][$i] = $start->getTimeOnly();
                            $timetableDef['end'][$i] = $end->getTimeOnly();
                            $timetableDef['number'][$i] = $currentPeriod->{'Period'};
                            $timetableDef['sequence'][$i] = 1;

                            $i++;
                        }
                    }
                }
            }
        }

        $timetableDef['periodCount'] = $maxPeriodsPerDay;
        $timetableDef['dayCount'] = $maxDays;

        return $timetableDef;

    }

    /**
     * Get the current day number for a given campus
     *
     * @param \Schoolbox\Campus $campus
     * @param int $date
     * @return int
     */
    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        $dayNumber = 0;

        $params = [];
        if ($date) {
            $params['Date'] = new Alaress_Date_Format($date, Alaress_Date_Format::TIMESTAMP);
        } else {
            $params['Date'] = new Alaress_Date_Format();
        }
        $api = new External_Denbigh2_Api('CurrentDay', $params);

        if (!$api->hasErrors()) {
            $dayNumber = $api->getResult()->data[0]->{'Day of Cycle'};
        }

        /* Just highlight day 1 by default, I guess
         */
        return $dayNumber;
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
     * Calculates the current period based on the current time
     *
     * The returned data structure is required for Dashboard Timetable Controller
     *
     * PeriodNumber
     * PeriodNumberSeq
     *
     * @param \Schoolbox\Campus $campus
     * @param int $now
     * @return array
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0)
    {
        $definition = $this->getTimetableDefinition(null, $campus);

        if (!$now) {
            $now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
        } else {
            $now = new \Alaress_Date_Format($now, 'U');
        }

        for ($i = 0; $i < count($definition['start']); $i++) {
            if ($definition['start'][$i] > $now->getDatabaseTime()) {
                return array(
                    'PeriodNumber' => $definition['number'][$i-1],
                    'PeriodNumberSeq' => $definition['sequence'][$i-1],
                );
            }
        }

        return array();
    }

    /**
     * Return a user's timetable for a specific day
     *
     * Depending on the role for $userId (staff or student) this summons the respective
     * method to spawn associated timetable
     *
     * Has the structure for each period
     *
     * periodName
     * periodStart
     * periodEnd
     * periodNumber
     * periodSequence
     * description
     * code
     * room
     * staffID
     * staffTitle
     * staffFirstname
     * staffSurname
     *
     * @param $externalUserId
     * @param $userId
     * @param \Schoolbox\Campus $campus
     * @param $dayNumber
     * @return mixed
     */
    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        $timetable = array();
        $definition = $this->getTimetableDefinition($externalUserId, $campus);

        if (!$definition) {
            return false;
        }

        // Build the timetable
        foreach ($definition['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $definition['desc'][$x];
            $timetable[$x]['periodStart']    = $definition['start'][$x];
            $timetable[$x]['periodEnd']      = $definition['end'][$x];
            $timetable[$x]['periodNumber']   = $definition['number'][$x];
            $timetable[$x]['periodSequence'] = $definition['sequence'][$x];
            $timetable[$x]['class']          = array();
        }

        /* Api connect
         */
        if ($this->getGroupHandler()->isStudent($userId)) {

            $api = new External_Denbigh2_Api('StudentTimetable',
                array('Student ID' => $externalUserId),
                array('RFMmax' => 0)
            );

            if (!$api->hasErrors()) {

                /* Data collectors
                 */
                $allPeriods = $api->getResult()->data;

                if (count($allPeriods)) {
                    foreach($allPeriods as $currentPeriod) {
                        if (!$dayNumber || $currentPeriod->{'Day Number'} == $dayNumber) {

                            /* You dumdums put the teacher's name concatted with the
                             * subject name >.< So instead of booming it and using the
                             * first shrapnel I'm just going to strreplace it out, so
                             * this will work even if they change their format to
                             * some other ridiculous thing
                             */
                            $className = trim(str_replace($currentPeriod->{'Teacher'}, '', $currentPeriod->{'TT Description'}));

                            /* wtf
                             * ...or I could preg_match it out of 1/3/0001, wherever that came from
                             */
                            $periodNumber = str_replace('Period', '', $currentPeriod->{'Period Name'});

                            $timetable[$periodNumber-1] = array(
                                'periodName' => $currentPeriod->{'Period Name'},
                                'periodStart' =>  $definition['start'][$periodNumber-1],
                                'periodEnd' => $definition['end'][$periodNumber-1],
                                'periodNumber' => $periodNumber,
                                'periodSequence' => 1,
                                'class' => array(
                                    array(
                                        'description' => $className,
                                        'code' => $currentPeriod->{'ClassCode'},
                                        'room' => $currentPeriod->{'Room'},
                                        'staffID' => $currentPeriod->{'TeacherCode'},
                                        'staffTitle' => '',
                                        'staffFirstname' => $currentPeriod->{'Teacher'},
                                        'staffSurname' => '',
                                    )
                                )
                            );
                        }
                    }
                }
            }

            return $timetable;
        }
        elseif ($this->getGroupHandler()->isStaff($userId)) {

            $api = new External_Denbigh2_Api('TimetableLessons',
                array('Teacher Code' => $externalUserId),
                array('RFMmax' => 0)
            );

            if (!$api->hasErrors()) {
                $allPeriods = $api->getResult()->data;

                /* Assemble into a 2D array
                 * Each row represents a Period across each Day in the cycle
                 *
                 * [Period1 [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * [Period2 [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * [Period3 [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * [PeriodN [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * ...
                 */
                if (count($allPeriods)) {
                    foreach($allPeriods as $currentPeriod) {
                        if (!$dayNumber || $currentPeriod->{'Day'} == $dayNumber) {
                            $periodName = "Period {$currentPeriod->{'Period'}}";
                            $periodNumber = $currentPeriod->{'Period'};

                            $timetable[$periodNumber-1] = array(
                                'periodName' => $periodName,
                                'periodStart' =>  $definition['start'][$periodNumber-1],
                                'periodEnd' => $definition['end'][$periodNumber-1],
                                'periodNumber' => $periodNumber,
                                'periodSequence' => 1,
                                'class' => array(
                                    array(
                                        'description' => $currentPeriod->{'Description for Staff TT'},
                                        'code' => $currentPeriod->{'Class'},
                                        'room' => "Room {$currentPeriod->{'Room'}}",
                                        'staffID' => $currentPeriod->{'Teacher Code'},
                                        'staffTitle' => '',
                                        'staffFirstname' => $currentPeriod->{'Teacher'},
                                        'staffSurname' => '',
                                    )
                                )
                            );
                        }
                    }
                }
            }

            return $timetable;
        }

        return '';
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
     * Return the ENTIRE timetable for a user and campus
     *
     * Assemble into a 2D array
     * Each row represents a Period across each Day in the cycle
     *
     * $periodIndex is a zero-based index integer which represents a $periodNumber
     * i.e. Period 1 is $periodIndex 0
     * Period 2 is $periodIndex 1
     * Period 3 is $periodIndex 2
     * ...
     *
     * Similarly with $dayIndex
     *
     * [Period1 [Day1 [Class1, Class2...], Day2, Day3, DayN...]]
     * [Period2 [Day1 [Class1, Class2...], Day2, Day3, DayN...]]
     * [Period3 [Day1 [Class1, Class2...], Day2, Day3, DayN...]]
     * [PeriodN [Day1 [Class1, Class2...], Day2, Day3, DayN...]]
     * ...
     * $timetable[$periodIndex]['periodName']
     * $timetable[$periodIndex]['periodStart']
     * $timetable[$periodIndex]['periodEnd']
     * $timetable[$periodIndex]['periodNumber']
     * $timetable[$periodIndex]['periodSequence']
     *
     * An array of classes for this period on this day
     * $timetable[$periodIndex]['day'][$dayIndex][] = array(
     *     description
     *     code
     *     campus
     *     room
     *     staffID
     *     staffFirstname
     *     staffSurname
     *     staffTitle
     * )
     *
     * @param $externalUserId
     * @param $userId
     * @param \Schoolbox\Campus $campus
     * @return mixed
     */
    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        $timetable = array();

        $definition = $this->getTimetableDefinition($externalUserId, $campus);

        if (!$definition) {
            return false;
        }

        /* Initialise $timetable
         */
        for ($i = 0; $i < count($definition['desc']); $i++) {
            $timetable[$i] = array(
                'periodName' => $definition['desc'][$i],
                'periodStart' => $definition['start'][$i],
                'periodEnd' => $definition['end'][$i],
                'periodNumber' => $definition['number'][$i],
                'periodSequence' => $definition['sequence'][$i],
                'day' => array_fill(1, $campus->getTimetableDay(), array()),
            );
        }

        /* Api connect
         * Guess what - both loops have different data structs
         */
        if ($this->getGroupHandler()->isStudent($userId)) {

            $api = new External_Denbigh2_Api('StudentTimetable',
                array('Student ID' => $externalUserId),
                array('RFMmax' => 0)
            );

            if (!$api->hasErrors()) {
                $allPeriods = $api->getResult()->data;

                /* Assemble into a 2D array
                 * Each row represents a Period across each Day in the cycle
                 *
                 * [Period1 [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * [Period2 [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * [Period3 [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * [PeriodN [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * ...
                 */
                if (count($allPeriods)) {
                    foreach($allPeriods as $currentPeriod) {

                        /* wtf
                         * ...or I could preg_match it out of 1/3/0001, wherever that came from
                         */
                        $periodNumber = trim(str_replace('Period', '', $currentPeriod->{'Period Name'}));
                        $periodIndex = $periodNumber-1;

                        $dayNumber = trim($currentPeriod->{'Day Number'});

                        /* Trim out classes that fall beyond Timetable Days
                         * specified in Admin/Lists/Campus
                         */
                        if ($dayNumber <= $campus->getTimetableDay()) {
                            $timetable[$periodIndex]['day'][$dayNumber][] = array(
                                'description' => $currentPeriod->{'ClassCode'},
                                'code' => $currentPeriod->{'ClassCode'},
                                'campus' => $campus->getName(),
                                'room' => "Room {$currentPeriod->{'Room'}}",
                                'staffID' => $currentPeriod->{'TeacherCode'},
                                'staffFirstname' => $currentPeriod->{'Teacher'},
                                'staffSurname' => '',
                                'staffTitle' => '',
                            );
                        }
                    }
                }
            }

            return $timetable;
        }
        else if ($this->getGroupHandler()->isStaff($userId)) {

            $api = new External_Denbigh2_Api('TimetableLessons',
                array('Teacher Code' => $externalUserId),
                array('RFMmax' => 0)
            );

            if (!$api->hasErrors()) {
                $allPeriods = $api->getResult()->data;

                /* Assemble into a 2D array
                 * Each row represents a Period across each Day in the cycle
                 *
                 * [Period1 [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * [Period2 [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * [Period3 [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * [PeriodN [Day1 [Classes], Day2 [Classes], Day3 [Classes], DayN [Classes]...]]
                 * ...
                 */
                if (count($allPeriods)) {
                    foreach($allPeriods as $currentPeriod) {

                        /* wtf
                         * ...or I could preg_match it out of 1/3/0001, wherever that came from
                         */
                        $periodNumber = trim(str_replace('Period', '', $currentPeriod->{'Period'}));
                        $periodIndex = $periodNumber-1;

                        $dayNumber = trim($currentPeriod->{'Day'});

                        /* Trim out classes that fall beyond Timetable Days
                         * specified in Admin/Lists/Campus
                         */
                        if ($dayNumber <= $campus->getTimetableDay()) {
                            $timetable[$periodIndex]['day'][$dayNumber][] = array(
                                'description' => $currentPeriod->{'Description for Staff TT'},
                                'code' => $currentPeriod->{'Class'},
                                'campus' => $campus->getName(),
                                'room' => "Room {$currentPeriod->{'Room'}}",
                                'staffID' => $currentPeriod->{'Teacher Code'},
                                'staffFirstname' => $currentPeriod->{'Teacher'},
                                'staffSurname' => '',
                                'staffTitle' => '',
                            );
                        }
                    }
                }
            }

            return $timetable;
        }

        return array();
    }

    /**
     * Gets the timetable for the given room.
     *
     * @param  array|string $roomCodes
     * @return array
     */
    public function getRoomTimetable($roomCodes)
    {
        // TODO: Implement getRoomTimetable() method.
    }

    /* Getters/Setters
     */

    /**
     * Set group handler
     *
     * @param \Group $groupHandler
     */
    private function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group handler
     *
     * @return \Group
     */
    private function getGroupHandler()
    {
        return $this->groupHandler;
    }
}