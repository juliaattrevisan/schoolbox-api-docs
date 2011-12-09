<?php
/**
 * Synchronise_External_Donvale_Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Donvale_Timetable extends Synchronise_External_Donvale_Abstract {
	private $timetableColumnList = array('Id', 'ClassCode', 'Day', 'Period', 'Room');

	/**
	 * Process input
	 *
	 * @return void
	 */
	public function process() {
		if ($this->isMatchingColumns($this->getTimetableColumnList())) {
			$this->setup();

			/* Create regular timetable
			 */
			$this->createTimetableCycle();

			$cycleId = Database::getInstance()->lastInsertId();

			$cycleLength = 0;

			$sequenceMax = 0;

			while ($propertyList = $this->getRow()) {
				$this->incrementLineCount();

				/* Check number of columns
				 */
				if ($this->isInvalidPropertyList($propertyList)) {
					continue;
				}

				/* Overwrite original var -_-
				 */
				$propertyList = $this->generatePropertyList($propertyList);

				if ($propertyList['Period'] == 'BS') {
					$propertyList['Period'] = 1;
					$propertyList['Title'] = "Before School";
				}
				else if ($propertyList['Period'] == 'HG') {
					$propertyList['Period'] = 2;
					$propertyList['Title'] = "Home Group";
				}
				else if ($propertyList['Period'] == 'AS') {
					$propertyList['Period'] = 9;
					$propertyList['Title'] = "After School";
				}
				else {
                    $propertyList['Title'] = "Period {$propertyList['Period']}";
					$propertyList['Period'] += 2;
				}

				/* Increase dimensions of timetable
				 */
				if ($cycleLength < $propertyList['Day']) {
					$cycleLength = $propertyList['Day'];
				}

				if ($sequenceMax < $propertyList['Period']) {
					$sequenceMax = $propertyList['Period'];
				}

				/* Find period
				 */
				$periodId = $this->getPeriodId($cycleId, $propertyList);

				/* Create period if it doesn't exist
				 */
				if (!is_numeric($periodId)) {
					$period = array(
						'cycleId' => $cycleId,
						'title' => $propertyList['Title'],
						'day' => $propertyList['Day'],
						'sequence' => $propertyList['Period'],
						'usable' => 1
					);

					$this->createTimetablePeriod($period);

					$periodId = Database::getInstance()->lastInsertId();
				}

                /* Find class
                 */
                $classId = $this->getClassId($propertyList);

                /* Check id
                 */
                if ($this->isInvalidPropertyListId($classId, $propertyList)) {
                    continue;
                }

				/* Create relationship
				 */
				$classPeriod = array(
					'classId' => $classId,
					'periodId' => $periodId
				);
if ( $classId == 1){
    echo '*******************************************************************';
    var_dump($classPeriod);
}
				$this->createClassPeriod($classPeriod);
			}

			/* Update cycle based on periods
			 */
			$cycle = array(
				'id' => $cycleId,
				'title' => "$cycleLength Day Cycle",
				'cycleLength' => $cycleLength
			);

			$this->updateTimetableCycle($cycle);

			/* Fill remaining periods
			 */
			for ( $i = 1; $i <= $cycleLength; $i++ ) {
				for ( $j = 1; $j <= $sequenceMax; $j++ ) {

					/* Find period
					 */
					$periodId = $this->getPeriodIdByDayAndSequence($cycleId, $i, $j);

					/* Create period if it doesn't exist
					 */
					if (!is_numeric($periodId) ) {
						$period = array(
							'cycleId' => $cycleId,
							'title' => "Period $j",
							'day' => $i,
							'sequence' => $j,
							'usable' => 1
						);

						$this->createTimetablePeriod($period);
					}
				}
			}

			print("{$this->getLineEnding()}{$this->getErrorCount()} of {$this->getLineCount()} class to period associations failed.{$this->getLineEnding()}");
		}

		/* Unrecognized format */
		else {
			die("Timetable file is not in a recognised format.{$this->getLineEnding()}");
		}

		echo "{$this->getLineEnding()}{$this->getLineEnding()}";
	}

	/**
	 * Setup
	 *
	 * @return void
	 */
	private function setup() {
		Database::getInstance()->query("TRUNCATE class_period");
		Database::getInstance()->query("TRUNCATE timetable_period");
		Database::getInstance()->query("TRUNCATE timetable_cycle");
	}

	/**
	 * Get timetbale column list
	 *
	 * @return array
	 */
	private function getTimetableColumnList() {
		return $this->timetableColumnList;
	}

	/**
	 * Check if the updated propertyList id is valid
	 *
	 * @param  $classId
	 * @param  $propertyList
	 * @return bool
	 */
	private function isInvalidPropertyListId($classId, $propertyList) {
		if (!is_numeric($classId)) {
			print("Class ({$propertyList['ClassCode']}) not found at line #{$this->getLineCount()} in {$this->getFileName()}{$this->getLineEnding()}");

			$this->incrementErrorCount();

			return true;
		}

		return false;
	}

	/**
	 * Create timetable cycle
	 *
	 * @return void
	 */
	private function createTimetableCycle() {
		Database::getInstance()->query("
			INSERT INTO timetable_cycle (title, cycle_length, regular)
			VALUES ('', 0, 1)
		");
	}

	/**
	 * Get class id
	 *
	 * @param  $propertyList
	 * @return string
	 */
	private function getClassId($propertyList) {
		return Database::getInstance()->query("
				SELECT id
				FROM class
				WHERE code = '{$propertyList['ClassCode']}'
			")->fetchColumn(0);
	}

	/**
	 * Get period id
	 *
	 * @param  $cycleId
	 * @param  $propertyList
	 * @return string
	 */
	private function getPeriodId($cycleId, $propertyList) {
 		return Database::getInstance()->query("
					SELECT id
					FROM timetable_period
					WHERE cycle_id = $cycleId
							AND day = '{$propertyList['Day']}'
							AND sequence = '{$propertyList['Period']}'
				")->fetchColumn(0);
	}

	/**
	 * Create timetable period
	 *
	 * @param  $period
	 * @return bool
	 */
	private function createTimetablePeriod($period) {
		Database::getInstance()->prepare("
			INSERT INTO timetable_period (cycle_id, title, day, sequence, usable)
			VALUES (:cycleId, :title, :day, :sequence, :usable)
		")->execute($period);
	}

	/**
	 * Create class period
	 *
	 * @param  $classPeriod
	 * @return bool
	 */
	private function createClassPeriod($classPeriod) {
		Database::getInstance()->prepare("
			INSERT INTO class_period (class_id, period_id)
			VALUES (:classId, :periodId)
		")->execute($classPeriod);
	}

	/**
	 * Update timetable cycle
	 *
	 * @param  $cycle
	 * @return void
	 */
	private function updateTimetableCycle($cycle) {
		Database::getInstance()->prepare("
			UPDATE timetable_cycle
			SET title = :title, cycle_length = :cycleLength
			WHERE id = :id
		")->execute($cycle);
	}

	/**
	 * Get period id by day and sequence
	 *
	 * @param  $cycleId
	 * @param  $day
	 * @param  $sequence
	 * @return string
	 */
	private function getPeriodIdByDayAndSequence($cycleId, $day, $sequence) {
		return Database::getInstance()->query("
			SELECT id
			FROM timetable_period
			WHERE cycle_id = $cycleId
				AND day = $day
				AND sequence = $sequence
		")->fetchColumn(0);
	}
}
