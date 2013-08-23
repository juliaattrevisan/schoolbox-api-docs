<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:40 PM
 * To change this template use File | Settings | File Templates.
 */
 
class External_Timetable {
	private $database;

	/**
	 * Constructor
	 *
	 * @throws Exception
	 * @param String $databaseName
	 */
	public function __construct ($databaseName = '') {
		if ( empty($databaseName) ) {
			$databaseName = Config::get('external_type');
		}

		$databaseClass = 'External_'.ucfirst($databaseName).'_Timetable';

		if ( class_exists($databaseClass, true) ) {
			try {
				$database = new $databaseClass;
				$this->setDatabase( $database );
			}
			catch(\Schoolbox\External\Exception $e) {
				$this->setDatabase(new External_Offline_Timetable());
			}
		} else {
			$this->setDatabase(new External_Offline_Timetable());
		}
	}

	/**
	 * No usages found
	 * @param Integer $campusId
	 * @return Array
	 */
	function getTimetableDefinition ($campusId, $day) {
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
	 * Returns a user's complete timetable
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
