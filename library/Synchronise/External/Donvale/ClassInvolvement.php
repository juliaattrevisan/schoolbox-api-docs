<?php
/**
 * Synchronise_External_Donvale_ClassInvolvement
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Donvale_ClassInvolvement extends Synchronise_External_Donvale_Abstract {
	private $involvementColumnList = array('Student Code', 'Class Codes');

	/**
	 * Process input
	 *
	 * @return void
	 */
	public function process() {

		/* Class format
		 */
		if ($this->isMatchingColumns($this->getInvolvementColumnList())) {
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

				$user = $this->getUserHandler()->getBySynergy($propertyList['Student Code']);

                $involvement = array(
                    'userId' => $user['uid'],
                    'classId' => $this->getClassId($propertyList)
                );

				/* Check id
				 */
				if ($this->isInvalidClassId($involvement, $propertyList)) {
					continue;
				}

				/* Update involvement
				 */
				$this->updateInvolvement($involvement);
			}

			print("{$this->getLineEnding()}{$this->getErrorCount()} of {$this->getLineCount()} student to class associations failed.{$this->getLineEnding()}");
		}

		/* Unrecognized format */
		else {
			die("Class involvement file is not in a recognised format.{$this->getLineEnding()}");
		}

		echo "{$this->getLineEnding()}{$this->getLineEnding()}";
	}

	/**
	 * Update involvement
	 *
	 * @param  $involvement
	 * @return void
	 */
	private function updateInvolvement($involvement) {
		Database::getInstance()->prepare("
			INSERT INTO class_user (class_id, user_id)
			VALUES (:classId, :userId)
		")->execute($involvement);
	}

	/**
	 * Get involvement column list
	 *
	 * @return array
	 */
	private function getInvolvementColumnList() {
		return $this->involvementColumnList;
	}

	/**
	 * Check if the updated propertyList id is valid
	 *
	 * @param  $involvement
	 * @param  $propertyList
	 * @return bool
	 */
	private function isInvalidPropertyListId($involvement, $propertyList) {
		if (!is_numeric($involvement['userId'])) {
			print("User ({$propertyList['Student Code']}) not found at line #{$this->getLineCount()} in {$this->getFileName()}{$this->getLineEnding()}");

			$this->incrementErrorCount();

			return true;
		}

		return false;
	}

	/**
	 * Check if the updated propertyList id is valid
	 *
	 * @param  $involvement
	 * @param  $propertyList
	 * @return bool
	 */
	private function isInvalidClassId($involvement, $propertyList) {
		if (!is_numeric($involvement['classId']) ) {
			print("Class ({$propertyList['Class Codes']}) not found at line #{$this->getLineCount()} in {$this->getFileName()}{$this->getLineEnding()}");

			$this->incrementErrorCount();

			return true;
		}

		return false;
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
					WHERE code = '{$propertyList['Class Codes']}'
					LIMIT 1
				")->fetchColumn(0);
	}
}
