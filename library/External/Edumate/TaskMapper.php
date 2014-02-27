<?php
/**
 * TaskMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Edumate_TaskMapper extends \Schoolbox\Mapper {

	/**
	 * Find out if an Edumate Task already exists as a SubmissionBox
	 *
	 * @param $edumateTaskId
	 * @param $categoryCode
	 * @return bool
	 */
	public function isTaskExists($edumateTaskId, $categoryCode) {
		$query = "
			SELECT COUNT(*) AS num
			FROM submission_box_edumate_task
			WHERE task_id = :edumateTaskId
			AND category_code = :categoryCode
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':edumateTaskId', $edumateTaskId);
		$statement->bindParam(':categoryCode', $categoryCode);
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
	 * @param $edumateTaskId
	 * @param $categoryCode
	 * @return null
	 */
	public function findSubmissionBoxFromTask($edumateTaskId, $categoryCode) {
		$query = "
			SELECT submission_box_id
			FROM submission_box_edumate_task
			WHERE task_id = :edumateTaskId
			AND category_code = :categoryCode
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':edumateTaskId', $edumateTaskId);
		$statement->bindParam(':categoryCode', $categoryCode);
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
	 * @param $edumateTaskId
	 * @param $categoryCode
	 */
	public function createRelation($submissionBox, $edumateTaskId, $categoryCode) {
		$query = "
			INSERT INTO submission_box_edumate_task (submission_box_id, task_id, category_code)
			VALUES (:submissionBoxId, :edumateTaskId, :categoryCode)
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':submissionBoxId', $submissionBox->getId());
		$statement->bindParam(':edumateTaskId', $edumateTaskId);
		$statement->bindParam(':categoryCode', $categoryCode);
		$statement->execute();
	}

	/**
	 * Find out if an Edumate Task already exists as a SubmissionBox
	 *
	 * @param $edumateTaskId
	 * @param $categoryCode
	 * @return bool
	 */
	public function isTaskReturnExists($edumateTaskId, $categoryCode) {
		$query = "
			SELECT COUNT(*) AS num
			FROM submission_return_edumate_task
			WHERE task_id = :edumateTaskId
			AND category_code = :categoryCode
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':edumateTaskId', $edumateTaskId);
		$statement->bindParam(':categoryCode', $categoryCode);
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
	 * @param $edumateTaskId
	 * @param $categoryCode
	 * @return null
	 */
	public function findSubmissionReturnFromTask($edumateTaskId, $categoryCode) {
		$query = "
			SELECT submission_return_id
			FROM submission_return_edumate_task
			WHERE task_id = :edumateTaskId
			AND category_code = :categoryCode
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':edumateTaskId', $edumateTaskId);
		$statement->bindParam(':categoryCode', $categoryCode);
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
	 * Create a relation between a SubmissionReturn and an Edumate Task
	 *
	 * @param $submissionReturn
	 * @param $edumateTaskId
	 * @param $categoryCode
	 */
	public function createReturnRelation($submissionReturn, $edumateTaskId, $categoryCode) {
		$query = "
			INSERT INTO submission_return_edumate_task (submission_return_id, task_id, category_code)
			VALUES (:submissionReturnId, :edumateTaskId, :categoryCode)
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':submissionReturnId', $submissionReturn->getId());
		$statement->bindParam(':edumateTaskId', $edumateTaskId);
		$statement->bindParam(':categoryCode', $categoryCode);
		$statement->execute();
	}
}