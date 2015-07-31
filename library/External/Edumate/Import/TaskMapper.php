<?php
/**
 * TaskMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Edumate_Import_TaskMapper extends \Schoolbox\Mapper
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
	 * Find out if an Edumate Task already exists as a SubmissionBox
	 *
	 * @param External\Edumate\Import\EdumateTask $edumateTask
	 * @return bool
	 */
	public function isTaskBoxExists(\External\Edumate\Import\EdumateTask $edumateTask) {
		$query = "
			SELECT COUNT(*) AS num
			FROM submission_box_edumate_task
			WHERE task_id = :edumateTaskId
			AND category_code = :categoryCode
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':edumateTaskId', $edumateTask->getTaskId());
		$statement->bindParam(':categoryCode', $edumateTask->getClassCode());
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
	 * Find out if a particular Edumate Task has more recent data than the
	 * Submission Box already in Schoolbox
	 *
	 * @param External\Edumate\Import\EdumateTask $edumateTask
	 * @return bool
	 */
	public function isNeedUpdate(\External\Edumate\Import\EdumateTask $edumateTask) {
		$query = "
			SELECT COUNT(*) AS num
			FROM submission_box_edumate_task
			WHERE task_id = :edumateTaskId
			AND category_code = :categoryCode
			AND updated_at < :updatedAt
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':edumateTaskId', $edumateTask->getTaskId());
		$statement->bindParam(':categoryCode', $edumateTask->getClassCode());
		$statement->bindParam(':updatedAt', $edumateTask->getLastUpdatedDate());
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
	 * @param External\Edumate\Import\EdumateTask $edumateTask
	 * @return null
	 */
	public function findBoxFromTask(\External\Edumate\Import\EdumateTask $edumateTask) {
		$query = "
			SELECT submission_box_id
			FROM submission_box_edumate_task
			WHERE task_id = :edumateTaskId
			AND category_code = :categoryCode
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':edumateTaskId', $edumateTask->getTaskId());
		$statement->bindParam(':categoryCode', $edumateTask->getClassCode());
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
	 * Create a relation between a SubmissionBox and an Edumate Task
	 *
	 * @param $submissionBox
	 * @param External\Edumate\Import\EdumateTask $edumateTask
	 */
	public function createBoxRelation($submissionBox, \External\Edumate\Import\EdumateTask $edumateTask) {
		$query = "
			INSERT INTO submission_box_edumate_task (submission_box_id, task_id, category_code, updated_at)
			VALUES (:submissionBoxId, :edumateTaskId, :categoryCode, :updatedAt)
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':submissionBoxId', $submissionBox->getId());
		$statement->bindParam(':edumateTaskId', $edumateTask->getTaskId());
		$statement->bindParam(':categoryCode', $edumateTask->getClassCode());
		$statement->bindParam(':updatedAt', $edumateTask->getLastUpdatedDate());
		$statement->execute();
	}

	/**
	 * Create a relation between a SubmissionBox and an Edumate Task
	 *
	 * @param $submissionBox
	 * @param External\Edumate\Import\EdumateTask $edumateTask
	 */
	public function updateBoxRelation($submissionBox, \External\Edumate\Import\EdumateTask $edumateTask) {
		$query = "
			UPDATE submission_box_edumate_task
			SET updated_at = :updatedAt
			WHERE submission_box_id = :submissionBoxId
			AND task_id = :edumateTaskId
			AND category_code = :categoryCode
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':submissionBoxId', $submissionBox->getId());
		$statement->bindParam(':edumateTaskId', $edumateTask->getTaskId());
		$statement->bindParam(':categoryCode', $edumateTask->getClassCode());
		$statement->bindParam(':updatedAt', $edumateTask->getLastUpdatedDate());
		$statement->execute();
	}
}