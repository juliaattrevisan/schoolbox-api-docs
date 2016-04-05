<?php
/**
 * TaskMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Synergetic_Import_MarksMapper extends \Schoolbox\Mapper
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
	 * @param \External\Synergetic\Import\SynergeticMark $synergeticMark
	 * @return bool
	 */
	public function isTaskReturnExists(\External\Synergetic\Import\SynergeticMark $synergeticMark) {
		$query = "
			SELECT COUNT(*) AS num
			FROM submission_return_synergetic_task
			WHERE task_id = :synergeticTaskId
			AND category_code = :categoryCode
			AND student_id = :studentId
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':synergeticTaskId', $synergeticMark->getTaskId());
		$statement->bindParam(':categoryCode', $synergeticMark->getClassCode());
		$statement->bindParam(':studentId', $synergeticMark->getStudentId());
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
	 * Find a Submission Return associated with a Task ID/Category Code tuple
	 *
	 * @param \External\Synergetic\Import\SynergeticMark $synergeticMark
	 * @return null
	 */
	public function findReturnFromTask(\External\Synergetic\Import\SynergeticMark $synergeticMark) {
		$query = "
			SELECT submission_return_id
			FROM submission_return_synergetic_task
			WHERE task_id = :synergeticTaskId
			AND category_code = :categoryCode
			AND student_id = :studentId
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':synergeticTaskId', $synergeticMark->getTaskId());
		$statement->bindParam(':categoryCode', $synergeticMark->getClassCode());
		$statement->bindParam(':studentId', $synergeticMark->getStudentId());
		$statement->execute();

		$row = $statement->fetch();

		if ($row !== false) {
			if ($row['submission_return_id']) {
				return $row['submission_return_id'];
			}
		}

		return null;
	}

	/**
	 * Create a relation between a SubmissionReturn and an Synergetic Task
	 *
	 * @param $submissionReturn
	 * @param \External\Synergetic\Import\SynergeticMark $synergeticMark
	 */
	public function createReturnRelation($submissionReturn, \External\Synergetic\Import\SynergeticMark $synergeticMark) {
		$query = "
			INSERT INTO submission_return_synergetic_task (submission_return_id, task_id, category_code, student_id)
			VALUES (:submissionReturnId, :synergeticTaskId, :categoryCode, :studentId)
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':submissionReturnId', $submissionReturn->getId());
		$statement->bindParam(':synergeticTaskId', $synergeticMark->getTaskId());
		$statement->bindParam(':categoryCode', $synergeticMark->getClassCode());
		$statement->bindParam(':studentId', $synergeticMark->getStudentId());
		$statement->execute();
	}
}