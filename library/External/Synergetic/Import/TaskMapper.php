<?php
/**
 * TaskMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Synergetic_Import_TaskMapper extends \Schoolbox\Mapper
{
	/**
	 * Setup method. Implementing classes to define their SQL map here.
	 *
	 * @return void
	 */
	protected function mapping()
	{
		// nothing goes here
	}

	/**
	 * Find out if an Synergetic Task already exists as a SubmissionBox
	 *
	 * @param External\Synergetic\Import\SynergeticTask $synergeticTask
	 * @return bool
	 */
	public function isTaskBoxExists(\External\Synergetic\Import\SynergeticTask $synergeticTask) {
		$query = "
			SELECT COUNT(*) AS num
			FROM submission_box_synergetic_task
			WHERE task_id = :synergeticTaskId
			AND category_code = :categoryCode
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':synergeticTaskId', $synergeticTask->getTaskId());
		$statement->bindParam(':categoryCode', $synergeticTask->getClassCode());
		$statement->execute();

		$row = $statement->fetch();

		if ($row !== false) {
			if ($row['num'] > 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find a Submission Box associated with a Task ID/Category Code tuple
	 *
	 * @param External\Synergetic\Import\SynergeticTask $synergeticTask
	 * @return null
	 */
	public function findBoxFromTask(\External\Synergetic\Import\SynergeticTask $synergeticTask) {
		$query = "
			SELECT submission_box_id
			FROM submission_box_synergetic_task
			WHERE task_id = :synergeticTaskId
			AND category_code = :categoryCode
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':synergeticTaskId', $synergeticTask->getTaskId());
		$statement->bindParam(':categoryCode', $synergeticTask->getClassCode());
		$statement->execute();

		$row = $statement->fetch();

		if ($row !== false) {
			if ($row['submission_box_id']) {
				return $row['submission_box_id'];
			}
		}

		return null;
	}

	/**
	 * Create a relation between a SubmissionBox and an Synergetic Task
	 *
	 * @param \Schoolbox\Assessment\Component\SubmissionBox $submissionBox
	 * @param External\Synergetic\Import\SynergeticTask $synergeticTask
	 */
	public function createBoxRelation(\Schoolbox\Assessment\Component\SubmissionBox $submissionBox, \External\Synergetic\Import\SynergeticTask $synergeticTask) {
		$query = "
			INSERT INTO submission_box_synergetic_task (submission_box_id, task_id, category_code)
			VALUES (:submissionBoxId, :synergeticTaskId, :categoryCode)
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':submissionBoxId', $submissionBox->getId());
		$statement->bindParam(':synergeticTaskId', $synergeticTask->getTaskId());
		$statement->bindParam(':categoryCode', $synergeticTask->getClassCode());
		$statement->execute();
	}
}