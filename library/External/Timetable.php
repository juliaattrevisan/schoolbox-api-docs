<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:40 PM
 * To change this template use File | Settings | File Templates.
 */
 
class External_Timetable implements External_TimetableInterface {
	private $database;

	/**
	 * Constructor
	 *
	 * @throws Exception
	 * @param String $databaseName
	 */
	public function __construct($databaseName = '') {
		$factory = new External_DatabaseFactory();
		$this->setDatabase($factory->buildTimetableConnector($databaseName));
	}

	/**
	 * No usages found
	 * @param Integer $campusId
	 * @return Array
	 */
	function getTimetableDefinition ($campusId, $day = 0) {
		return $this->getDatabase()->getTimetableDefinition($campusId, $day);
	}

	/**
	 * Calculates the current day number in the cycle
	 *
	 * @param Integer $campusId
	 * @param String $date
	 * @return Integer
	 */
	function getDayNumber ($campusId, $date = 0) {
		return $this->getDatabase()->getDayNumber($campusId, $date);
	}

	/**
	 * Determines the period number
	 *
	 * @param Integer $campusId
	 * @return Integer
	 */
	function getPeriodNumber ($campusId) {
		return $this->getDatabase()->getPeriodNumber($campusId);
	}

	/**
	 * Gets the next (including today) or previous (not including today) date
	 * that a timetable day number occurs on.
	 *
	 * @todo this should be moved to a 5.4 trait, and used by External_*_Timetable
	 *
	 * @param int  $campusId The campus ID to get the date for.
	 * @param int  $day      The day number to get the date for.
	 * @param int  $date     A Unix timestamp representing the date to get, or
	 *                       today if not set.
	 * @param bool $previous True if previous date is to be found, instead of
	 *                       next date.
	 *
	 * @throws InvalidArgumentException if $date or $campusId are invalid
	 * @throws RangeException           if $day isn't found in ±365 days of $date
	 *
	 * @return DateTime
	 */
	protected function getDateForDayNumber($campusId, $day, $date = null, $previous = false) {
		if (!isset($date)) {
			$date = time();
		}
		$thisDate = new DateTime();
		if (!$thisDate->setTimestamp($date)) {
			throw new InvalidArgumentException('Invalid format for date');
		}

		// Checking previous occurence? Start one day before
		if ($previous) {
			$thisDate->modify('-1 day');
		}

		$isFound = false;
		if ($day > 0) {
			// Start at today, and get number of days
			// Try for a year, that's long enough.
			$i = 0;
			while ($i++ < 365) {
				$thisDayNumber = $this->getDayNumber($campusId, $thisDate->getTimestamp());
				if ($thisDayNumber === false) {
					throw new InvalidArgumentException('Invalid campus requested');
				}
				if ($thisDayNumber == $day) {
					$isFound = true;
					break;
				}
				$thisDate->modify($previous ? '-1 day' : '+1 day');
			}
		}
		if (!$isFound) {
			throw new RangeException('No date found for requested timetable day');
		}
		return $thisDate;
	}

	/**
	 * @see $this->getDateForDayNumber
	 */
	public function getPreviousDateForDayNumber($campusId, $day, $date = null) {
		return $this->getDateForDayNumber($campusId, $day, $date, true);
	}

	/**
	 * @see $this->getDateForDayNumber
	 */
	public function getNextDateForDayNumber($campusId, $day, $date = null) {
		return $this->getDateForDayNumber($campusId, $day, $date, false);
	}

	/**
	 * Returns a user's complete timetable
	 *
     * The structure of this data is as follows
     * Array[]['periodName']
     * Array[]['periodStart']
     * Array[]['periodEnd']
     * Array[]['periodNumber']
     * Array[]['periodSequence']
     * Array[]['day'][][]['description']
     * Array[]['day'][][]['code']
     * Array[]['day'][][]['campus']
     * Array[]['day'][][]['description']
     *
	 * @param Integer $externalUserId
	 * @param Integer $userId
	 * @param Integer $campusId
	 * @return Array
	 */
	function getFullTimetable ($externalUserId, $userId, $campusId) {
		return $this->getDatabase()->getFullTimetable($externalUserId, $userId, $campusId);
	}

	/**
	 * Returns a user's timetable for a particular day
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
     * Note that you can have multiple classes per period.
     *
	 *
	 * @param Integer $externalUserId
	 * @param Integer $userId
	 * @param Integer $campusId
	 * @param Integer $dayNumber
	 * @return Array
	 */
	function getDayTimetable ($externalUserId, $userId, $campusId, $dayNumber) {
		return $this->getDatabase()->getDayTimetable($externalUserId, $userId, $campusId, $dayNumber);
	}


	/**
	 * No usages found
	 *
	 * @param Integer $externalUserId
	 * @param Integer $dayNumber
	 * @return Array
	 */
	function getDaySubjects ($externalUserId, $dayNumber) {
		return $this->getDatabase()->getDaySubjects($externalUserId, $dayNumber);
	}

	/**
	 * No usages found
	 * 
	 * @param String $classCode
	 * @param Integer $dayNumber
	 * @return Array
	 */
	function getSubjectPeriod($classCode, $dayNumber) {
		return $this->getDatabase()->getSubjectPeriod($classCode, $dayNumber);
	}

	/**
	 * No usages found
	 * 
	 * @param $classCode
	 * @return String
	 */
	function getSubjectName($classCode) {
		return $this->getDatabase()->getSubjectName($classCode);
	}

	/**
	 * Gets the timetable for the given room.
	 *
	 * @param  string $roomCode
	 * @return array
	 */
	public function getRoomTimetable($roomCode) {
		return $this->getDatabase()->getRoomTimetable($roomCode);
	}

    public function __call ($methodName, $argumentList) {
		$database = $this->getDatabase();

		$methodReflection = new ReflectionMethod($database, $methodName);

		return $methodReflection->invokeArgs($database, $argumentList);
	}

	/**
	 * @param External_TimetableInterface $database
	 * @return void
	 */
	private function setDatabase ($database) {
		$this->database = $database;
	}

	/**
	 * @return External_TimetableInterface
	 */
	private function getDatabase () {
		return $this->database;
	}

}
