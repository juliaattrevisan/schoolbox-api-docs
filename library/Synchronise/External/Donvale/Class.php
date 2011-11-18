<?php
/**
 * Synchronise_External_Donvale_Class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Donvale_Class extends Synchronise_External_Donvale_Abstract {
	private $classColumnList = array('Class Code', 'Subject Code', 'Teacher');

	/**
	 * Process input
	 *
	 * @return void
	 */
	public function process() {

		/* Class format
		 */
		if ($this->isMatchingColumns($this->getClassColumnList())) {
			$this->setup();

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

				$class = array(
					'name' => $propertyList['Class Code'],
					'code' => $propertyList['Class Code'],
				);

				$this->createClass($class);

				$class['id'] = Database::getInstance()->lastInsertId();

				/* Add teacher to class */
				$teacherId = $this->getTeacherId($propertyList);

				/* Check id
				 */
				if ($this->isInvalidPropertyListId($teacherId, $propertyList)) {
					continue;
				}

				$classUser = array(
					'classId' => $class['id'],
					'userId' => $teacherId
				);

				/* Update class user
				 */
				$this->updateClassUser($classUser);
			}

			print("{$this->getLineEnding()}{$this->getErrorCount()} of {$this->getLineCount()} classes failed to load properly.{$this->getLineEnding()}");
		}

		/* Unrecognized format */
		else {
			die("Class file is not in a recognised format.{$this->getLineEnding()}");
		}

		echo "{$this->getLineEnding()}{$this->getLineEnding()}";
	}

	/**
	 * Setup
	 *
	 * @return void
	 */
	private function setup() {
		Database::getInstance()->query("TRUNCATE class");
		Database::getInstance()->query("TRUNCATE class_user");
	}

	/**
	 * Create class
	 *
	 * @param  $class
	 * @return void
	 */
	private function createClass($class) {
		Database::getInstance()->prepare("
			INSERT INTO class (name, code)
			VALUES (:name, :code)
		")->execute($class);
	}

	/**
	 * Get teacher id
	 *
	 * @param  $propertyList
	 * @return string
	 */
	private function getTeacherId($propertyList) {
		return Database::getInstance()->query("
			SELECT id
			FROM user
			WHERE username LIKE '{$propertyList['Teacher']}'
		")->fetchColumn(0);
	}

	/**
	 * Update class user
	 *
	 * @param  $classUser
	 * @return void
	 */
	private function updateClassUser($classUser) {
		Database::getInstance()->prepare("
			INSERT INTO class_user (class_id, user_id)
			VALUES (:classId, :userId)
		")->execute($classUser);
	}

	/**
	 * Get class column list
	 *
	 * @return array
	 */
	private function getClassColumnList() {
		return $this->classColumnList;
	}

	/**
	 * Check if the updated propertyList id is valid
	 *
	 * @param  $teacherId
	 * @param  $propertyList
	 * @return bool
	 */
	private function isInvalidPropertyListId($teacherId, $propertyList) {
		if (!is_numeric($teacherId)) {
			print("Teacher not found ({$propertyList['Teacher']}) at line #{$this->getLineCount()} in {$this->getFileName()}{$this->getLineEnding()}");

			$this->incrementErrorCount();

			return true;
		}

		return false;
	}
}
