<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to Denbigh timetable info	*
*                                                           *
************************************************************/

class External_Schoolbox_Timetable implements External_TimetableInterface {
	private $database;

	private $group;

	public function __construct () {
		$this->setdatabase( Database::getInstance() );

		$this->setGroup( new Group );
	}

	public function getTimetableDefinition ($campusId) {
		$statement = $this->getDatabase()->prepare("
			SELECT tc.start, tc.reset, tc.finish,
				ty.title, ty.length, ty.regular
			FROM timetable_campus tc
			LEFT JOIN timetable_cycle ty ON ty.id = tc.cycle_id
			WHERE tc.campus_id = :campusId
		");

		$statement->execute( array(':campusId' => $campusId) );
		
		$definition = $statement->fetch(PDO::FETCH_ASSOC);

		/* Adapt to spec */
		$definition['dayCount'] = $definition['length'];

		return $definition;
	}

	public function getDayNumber ($campusId, $date = 0) {
		$definition = $this->getTimetableDefinition($campusId);

		if ( empty($date) ) {
			$date = time();
		}

		if ( empty($definition['reset']) ) {
			$start = strtotime($definition['start']);
		}
		else {
			$start = strtotime($definition['reset']);
		}

		$days = ceil( ($date - $start) / (60 * 60 * 24) );

		$businessDays = 5 * floor($days / 7);

		$excessDays = $days % 7;

		for ( $i = 0; $i < $excessDays; $i++ ) {
			$currentExcessDay = $date - (60 * 60 * 24 * $i);

			if ( date('N', $currentExcessDay) < 6 ) {
				$businessDays++;
			}
		}

		$currentDay = $businessDays % $definition['length'];

		return $currentDay;
	}

	public function getPeriodNumber ($campusId) {
		$definition = $this->getTimetableDefinition($campusId);

		if ( empty($definition['regular']) ) {
			return false;
		}
	}

	public function getFullTimetable ($externalUserId, $userId, $campusId) {
		/* Get current campus timetable */
		$periodStatement = $this->getDatabase()->prepare("
			SELECT tp.id, tp.title, tp.day, tp.sequence, tp.start, tp.finish, tp.usable
			FROM timetable_campus tc
			LEFT JOIN timetable_cycle ty ON ty.id = tc.cycle_id
			LEFT JOIN timetable_period tp ON tp.cycle_id = ty.id
			WHERE tc.campus_id = :campusId
			ORDER BY tp.day ASC, tp.sequence ASC
		");

		$periodStatement->execute( array(
			':campusId' => $campusId,
		) );

		$periodList = $periodStatement->fetchAll(PDO::FETCH_ASSOC);

		/* Get user's classes */
		$classStatement = $this->getDatabase()->prepare("
			SELECT c.name, c.code, c.description, cp.period_id
			FROM class_user cl
			LEFT JOIN class c ON c.id = cl.class_id
			LEFT JOIN class_period cp ON cp.class_id = c.id
			WHERE cl.user_id = :userId
		");

		$classStatement->execute( array(
			':userId' => $userId,
		) );

		$classList = $classStatement->fetchAll(PDO::FETCH_ASSOC);

		$periodListAdaption = array();

		foreach ( $periodList as $period ) {
			$periodSequence = $period['sequence'];

			if ( !isset($periodListAdaption[$periodSequence]) ) {
				$periodListAdaption[$periodSequence] = array(
					'periodNumber'   => $period['sequence'],
					'periodName'     => $period['title'],
					'periodStart'    => $period['start'],
					'periodEnd'      => $period['finish'],
					'periodSequence' => 1,
					'day'            => array()
				);
			}

			$dayNumber = $period['day'];

			$periodListAdaption[$periodSequence]['day'][$dayNumber] = array( 'periodOn' => 1 );

			foreach ( $classList as $i => $class ) {
				if ( $class['period_id'] == $period['id'] ) {
					$periodListAdaption[$periodSequence]['day'][$dayNumber]['description'] = $class['name'];
					$periodListAdaption[$periodSequence]['day'][$dayNumber]['code'] = $class['code'];
					$periodListAdaption[$periodSequence]['day'][$dayNumber]['room'] = '';

					unset($classList[$i]);
				}
			}
		}

		return $periodListAdaption;
	}

	public function getDayTimetable ($externalUserId, $userId, $campusId, $dayNumber) {
		/* Get current campus timetable */
		$periodStatement = $this->getDatabase()->prepare("
			SELECT tp.id, tp.title, tp.day, tp.sequence, tp.start, tp.finish, tp.usable
			FROM timetable_campus tc
			LEFT JOIN timetable_cycle ty ON ty.id = tc.cycle_id
			LEFT JOIN timetable_period tp ON tp.cycle_id = ty.id
			WHERE tc.campus_id = :campusId
				AND tp.day = :day
			ORDER BY tp.day ASC, tp.sequence ASC
		");

		$periodStatement->execute( array(
			':campusId' => $campusId,
		    ':day' => $dayNumber
		) );

		$periodList = $periodStatement->fetchAll(PDO::FETCH_ASSOC);

		/* Get user's classes */
		$classStatement = $this->getDatabase()->prepare("
			SELECT c.name, c.code, c.description, cp.period_id
			FROM class_user cl
			LEFT JOIN class c ON c.id = cl.class_id
			LEFT JOIN class_period cp ON cp.class_id = c.id
			WHERE cl.user_id = :userId
		");

		$classStatement->execute( array(
			':userId' => $userId,
		) );

		$classList = $classStatement->fetchAll(PDO::FETCH_ASSOC);

		$periodListAdaption = array();

		foreach ( $periodList as $period ) {
			$periodAdaption = array(
				'periodNumber'   => $period['sequence'],
				'periodName'     => $period['title'],
				'periodStart'    => $period['start'],
				'periodEnd'      => $period['finish'],
				'periodSequence' => 1,
			);

			foreach ( $classList as $i => $class ) {
				if ( $class['period_id'] == $period['id'] ) {
					$periodAdaption['description'] = $class['name'];
					$periodAdaption['code'] = $class['code'];
					$periodAdaption['room'] = '';

					unset($classList[$i]);
				}
			}

			$periodListAdaption[] = $periodAdaption;
		}

		return $periodListAdaption;
	}

	public function getDaySubjects ($externalUserId, $dayNumber) {
		
	}

	public function getSubjectPeriod($classCode, $dayNumber) {

	}
	
	public function getSubjectName($classCode) {
		
	}
	
	/**
	 * @param Database $database
	 * @return void
	 */
	private function setDatabase (Database $database) {
		$this->database = $database;
	}

	/**
	 * @return Database
	 */
	private function getDatabase () {
		return $this->database;
	}

	/**
	 * @param Group $group
	 * @return void
	 */
	private function setGroup (Group $group) {
		$this->group = $group;
	}

	/**
	 * @return Group
	 */
	private function getGroup () {
		return $this->group;
	}
}

?>
