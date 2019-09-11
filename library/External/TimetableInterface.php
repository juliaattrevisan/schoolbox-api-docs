<?php

use Schoolbox\Campus;

/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:45 PM
 * To change this template use File | Settings | File Templates.
 */
interface External_TimetableInterface extends External\IConnector
{
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
     * @param string|null $externalUserId If set, the external user ID to get definition for.
     * @param Campus $campus
     * @param int $day
     * @return array
     */
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0): array;

    /**
     * Get the current day number for a given campus
     *
     * @param Schoolbox\Campus $campus
     * @param int $date
     * @return int
     */
    public function getDayNumber(Campus $campus, $date = 0);

    /**
     * Returns the name of the current day
     *
     * @param $dayNumber
     * @return string
     */
    public function getDayName($dayNumber): string;

    /**
     * Calculates the current period based on the current time
     *
     * The returned data structure is required for Dashboard Timetable Controller
     *
     * array['PeriodNumber']
     * array['PeriodNumberSeq']
     *
     * @param Schoolbox\Campus $campus
     * @param int $now
     * @param \Schoolbox\User $user
     * @return array
     */
    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array;


    /**
     * Return a user's timetable for a specific day
     *
     * Depending on the role for $userId (staff or student) this summons the respective
     * method to spawn associated timetable
     *
     * The structure of this data is as follows
     * Array[]['periodName']
     * Array[]['periodStart']
     * Array[]['periodEnd']
     * Array[]['periodNumber']
     * Array[]['periodSequence']
     * Array[]['class'][]['description']
     * Array[]['class'][]['code']
     * Array[]['class'][]['room']
     * Array[]['class'][]['campus']
     * Array[]['class'][]['staffID']
     *
     *
     * @param                   $externalUserId
     * @param                   $userId
     * @param Campus $campus
     * @param                   $dayNumber
     * @return array
     */
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber): array;

    /**
     * Return a user's timetable for a specific date
     *
     * This was developed as synergetic has substitutions and attendance data available in a stored proc which
     * is date dependent
     *
     * Depending on the role for $userId (staff or student) this summons the respective
     * method to spawn associated timetable
     *
     * Returns the following structure:
     *
     * @internal string      array[]['periodName']
     * @internal string      array[]['periodStart']
     * @internal string      array[]['periodEnd']
     * @internal null|int    array[]['periodNumber']
     * @internal null|int    array[]['periodSequence']
     * @internal string      array[]['class'][]['description']
     * @internal null|string array[]['class'][]['code']
     * @internal string      array[]['class'][]['room']
     * @internal string      array[]['class'][]['campus']
     * @internal null|string array[]['class'][]['staffID']
     * @internal null|string array[]['class'][]['staffTitle']
     * @internal null|string array[]['class'][]['staffFirstname']
     * @internal null|string array[]['class'][]['staffSurname']
     * @internal string      array[]['class'][]['SubstituteFlag']
     * @internal null|int    array[]['class'][]['SubstitutedFlag']
     * @internal null|int    array[]['class'][]['AttendanceMarked']
     * @internal null|bool   array[]['class'][]['AttendanceEnabled']
     * @internal null|bool   array[]['class'][]['AttendancePeriods']
     * @internal null|int    array[]['class'][]['ClassCancelled']
     *
     * @param string $externalUserId
     * @param int $userId
     * @param Schoolbox\Campus $campus
     * @param Alaress_Date_Format $date
     * @return array
     */
    public function getTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array;


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
     * @param Schoolbox\Campus $campus
     * @return array
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus): array;

    /**
     * Gets the timetable for the given room.
     *
     * @param  string[] $roomCodes
     * @return array
     */
    public function getRoomTimetable(array $roomCodes): array;
}
