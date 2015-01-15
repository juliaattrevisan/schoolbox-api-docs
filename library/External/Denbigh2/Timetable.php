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
     * An instance of CampusMapper
     *
     * @var null
     */
    private $campusMapper = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setGroupHandler(new Group());
        $this->setCampusMapper(new \Schoolbox\CampusMapper());
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
     * @param $campusId
     * @param int $day
     * @return array
     */
    public function getTimetableDefinition($campusId, $day = 0)
    {
        $campusMapper = new \Schoolbox\CampusMapper();
        $campus = $campusMapper->findById($campusId);

        $timetableDef = array();
        $maxPeriodsPerDay = 6;
        $maxDays = $campus->getTimetableDay();

        $api = new External_Denbigh2_Api('PeriodTimes', array());

        if (!count($api->getErrors())) {
            $allPeriods = $api->getResult()->data;

            /* Day-specific e.g. for Dashboard
             */
            $i = 0;

            foreach ($allPeriods as $currentPeriod) {
                if ($i < $maxPeriodsPerDay) {
                    if (!$day || $currentPeriod->{'Day of Cycle'} == $day) {
                        $timetableDef['desc'][$i] = "Period {$currentPeriod->{'Period'}}";
                        $timetableDef['start'][$i] = $currentPeriod->{'Period Start Time'};
                        $timetableDef['end'][$i] = $currentPeriod->{'Period End Time'};
                        $timetableDef['number'][$i] = $currentPeriod->{'Period'};
                        $timetableDef['sequence'][$i] = 1;

                        $i++;
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
     * @param $campusId
     * @param int $date
     * @return int
     */
    public function getDayNumber($campusId, $date = 0)
    {
        // TODO: Implement getDayNumber() method.
        return 1;
    }

    /**
     * Calculates the current period based on the current time
     *
     * The returned data structure is required for Dashboard Timetable Controller
     *
     * PeriodNumber
     * PeriodNumberSeq
     *
     * @param $campusId
     * @return array
     */
    public function getPeriodNumber($campusId)
    {
        // TODO: Implement getPeriodNumber() method.
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
     * @param $campusId
     * @param $dayNumber
     * @return mixed
     */
    public function getDayTimetable($externalUserId, $userId, $campusId, $dayNumber)
    {
        $api = false;
        $timetable = array();

        /* Api connect
         */
        if ($this->getGroupHandler()->isStudent($userId)) {
            $api = new External_Denbigh2_Api('StudentTimetable', array(
                'Student ID' => $externalUserId
            ));
        }
        elseif ($this->getGroupHandler()->isStaff($userId)) {
            $api = new External_Denbigh2_Api('StaffTimetable', array(
                'Staff Code' => $externalUserId
            ));
        }

        if ($api !== false) {
            $userMapper = new \Schoolbox\UserMapper();
            $user = $userMapper->findOneByColumn('synergy_id', $externalUserId);
            $user = $userMapper->findById($user->getId());
            $definition = $this->getTimetableDefinition($user->getCampusCollection()->first()->getId());

            if (!$definition) {
                return false;
            }

            if (!count($api->getErrors())) {

                // Build the timetable
                foreach ($definition['desc'] as $x => $name) {
                    $timetable[$x]['periodName']     = $definition['desc'][$x];
                    $timetable[$x]['periodStart']    = $definition['start'][$x];
                    $timetable[$x]['periodEnd']      = $definition['end'][$x];
                    $timetable[$x]['periodNumber']   = $definition['number'][$x];
                    $timetable[$x]['periodSequence'] = $definition['sequence'][$x];
                    $timetable[$x]['class']          = array();
                }

                /* Data collectors
                 */
                $allPeriods = $api->getResult()->data;

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

    /**
     * Return the ENTIRE timetable for a user and campus
     *
     * Assemble into a 2D array
     * Each row represents a Period across each Day in the cycle
     *
     * [Period1 [Day1, Day2, Day3, DayN...]]
     * [Period2 [Day1, Day2, Day3, DayN...]]
     * [Period3 [Day1, Day2, Day3, DayN...]]
     * [PeriodN [Day1, Day2, Day3, DayN...]]
     * ...
     * $timetable[$period]['periodName']
     * $timetable[$period]['periodStart']
     * $timetable[$period]['periodEnd']
     * $timetable[$period]['periodNumber']
     * $timetable[$period]['periodSequence']
     * $timetable[$period]['day'][$day][] = array()
     *
     * For each period, each day has the definition:
     *
     * periodOn
     * description
     * code
     * room
     * staffID
     * staffFirstname
     * staffSurname
     * staffTitle
     *
     * @param $externalUserId
     * @param $userId
     * @param $campusId
     * @return mixed
     */
    public function getFullTimetable($externalUserId, $userId, $campusId)
    {
        $timetable = array();

        $userMapper = new \Schoolbox\UserMapper();
        $user = $userMapper->findOneByColumn('synergy_id', $externalUserId);
        $user = $userMapper->findById($user->getId());
        $campus = $user->getCampusCollection()->first();
        $definition = $this->getTimetableDefinition($campus->getId());

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
                'day' => array_fill(0, $campus->getTimetableDay(), array()),
            );
        }

        /* Api connect
         */
        $api = false;

        if ($this->getGroupHandler()->isStudent($userId)) {
            $api = new External_Denbigh2_Api('StudentTimetable', array(
                'Student ID' => $externalUserId,
            ));
        }
        else if ($this->getGroupHandler()->isStaff($userId)) {
            $api = new External_Denbigh2_Api('StaffTimetable', array(
                'Staff Code' => $externalUserId,
            ));
        }

        if ($api !== false) {
            if (!count($api->getErrors())) {
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
                foreach($allPeriods as $currentPeriod) {

                    /* wtf
                     * ...or I could preg_match it out of 1/3/0001, wherever that came from
                     */
                    $periodNumber = trim(str_replace('Period', '', $currentPeriod->{'Period Name'}));
                    $periodIndex = $periodNumber-1;

                    $dayNumber = trim($currentPeriod->{'Day Number'});
                    $dayIndex = $dayNumber-1;

                    $timetable[$periodIndex]['day'][$dayIndex][] = array(
                        'description' => $currentPeriod->{'Period Name'},
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

        return $timetable;
    }

    /**
     * Retrieve the list of subjects for a user on a particular day
     *
     * Room
     * Description
     * PeriodNumber
     * ClassCode
     * ClassCampus
     *
     * @param $externalUserId
     * @param $dayNumber
     * @return array
     */
    public function getDaySubjects($externalUserId, $dayNumber)
    {
        // TODO: Implement getDaySubjects() method.
    }

    /**
     * Get the period for a class, given its $classCode and $dayNumber
     *
     * PeriodNumber
     *
     * @param $classCode
     * @param $dayNumber
     * @return array
     */
    public function getSubjectPeriod($classCode, $dayNumber)
    {
        // TODO: Implement getSubjectPeriod() method.
    }

    /**
     * Get the verbose name for the $classCode
     *
     * Description
     *
     * @param $classCode
     * @return string
     */
    public function getSubjectName($classCode)
    {
        $name = '';

        $api = new External_Denbigh2_Api('Classes', array(
            'Class' => $classCode
        ));

        if (!count($api->getErrors())) {
            $currentClass = $api->getResult()->data[0];

            $name = $currentClass->{'Subject Name'};
        }

        return $name;
    }

    /**
     * Gets the timetable for the given room.
     *
     * @param  string $roomCode
     * @return array
     */
    public function getRoomTimetable($roomCode)
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

    /**
     * Set campus mapper
     *
     * @param null $campusMapper
     */
    private function setCampusMapper($campusMapper)
    {
        $this->campusMapper = $campusMapper;
    }

    /**
     * Get campus mapper
     *
     * @return \Schoolbox\CampusMapper
     */
    private function getCampusMapper()
    {
        return $this->campusMapper;
    }

}