<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:45 PM
 * To change this template use File | Settings | File Templates.
 */

interface External_TimetableInterface {
	public function getTimetableDefinition ($campusId, $day=0);

	public function getDayNumber ($campusId, $date = 0);

	public function getPeriodNumber ($campusId, $now = 0);

	public function getDayTimetable ($externalUserId, $userId, $campusId, $dayNumber);

	public function getFullTimetable ($externalUserId, $userId, $campusId);

	public function getDaySubjects ($externalUserId, $dayNumber);

	public function getSubjectPeriod ($classCode, $dayNumber);

	public function getSubjectName ($classCode);

	/**
	 * Gets the timetable for the given room.
	 *
	 * @param  string $roomCode
	 * @return array
	 */
	public function getRoomTimetable($roomCode);
}
