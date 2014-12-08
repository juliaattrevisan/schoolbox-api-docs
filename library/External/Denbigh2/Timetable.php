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
     * @param $campusId
     * @param int $day
     * @return array
     */
    public function getTimetableDefinition($campusId, $day = 0)
    {
        // TODO: Implement getTimetableDefinition() method.
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
        if ($this->getGroupHandler()->isStudent($userId)) {
            return $this->getStudentTimetable($externalUserId, $dayNumber);
        } else {
            if ($this->getGroupHandler()->isStaff($userId)) {
                return $this->getStaffTimetable($externalUserId, $dayNumber);
            }
        }
    }

    public function getStaffTimetable($externalUserId, $dayNumber) {
        $api = new External_Denbigh2_Api('StaffTimetable', array(
            'Staff Code' => $externalUserId
        ));

        if (!count($api->getErrors())) {
            new \Alaress_Probe($api->getResult());
        }

        return;
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
        // TODO: Implement getFullTimetable() method.
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
        // TODO: Implement getSubjectName() method.
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
}