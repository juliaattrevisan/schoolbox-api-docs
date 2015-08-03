<?php
/**
 * TaskMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Edumate_Import_MarksMapper extends \Schoolbox\Mapper
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
	 * @param \External\Edumate\Import\EdumateMark $edumateMark
	 * @return bool
	 */
	public function isTaskReturnExists(\External\Edumate\Import\EdumateMark $edumateMark) {
		$query = "
			SELECT COUNT(*) AS num
			FROM submission_return_edumate_task
			WHERE task_id = :edumateTaskId
			AND category_code = :categoryCode
			AND student_id = :studentId
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':edumateTaskId', $edumateMark->getTaskId());
		$statement->bindParam(':categoryCode', $edumateMark->getClassCode());
		$statement->bindParam(':studentId', $edumateMark->getStudentId());
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
	 * @param \External\Edumate\Import\EdumateMark $edumateMark
	 * @return bool
	 */
	public function isNeedUpdate(\External\Edumate\Import\EdumateMark $edumateMark) {
		$query = "
			SELECT COUNT(*) AS num
			FROM submission_return_edumate_task
			WHERE task_id = :edumateTaskId
			AND category_code = :categoryCode
			AND student_id = :studentId
			AND updated_at < :updatedAt
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':edumateTaskId', $edumateMark->getTaskId());
		$statement->bindParam(':categoryCode', $edumateMark->getClassCode());
		$statement->bindParam(':studentId', $edumateMark->getStudentId());
		$statement->bindParam(':updatedAt', $edumateMark->getLastUpdatedDate());
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
	 * @param \External\Edumate\Import\EdumateMark $edumateMark
	 * @return null
	 */
	public function findReturnFromTask(\External\Edumate\Import\EdumateMark $edumateMark) {
		$query = "
			SELECT submission_return_id
			FROM submission_return_edumate_task
			WHERE task_id = :edumateTaskId
			AND category_code = :categoryCode
			AND student_id = :studentId
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':edumateTaskId', $edumateMark->getTaskId());
		$statement->bindParam(':categoryCode', $edumateMark->getClassCode());
		$statement->bindParam(':studentId', $edumateMark->getStudentId());
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
	 * @param \External\Edumate\Import\EdumateMark $edumateMark
	 */
	public function createReturnRelation($submissionReturn, \External\Edumate\Import\EdumateMark $edumateMark) {
		$query = "
			INSERT INTO submission_return_edumate_task (submission_return_id, task_id, category_code, student_id, updated_at)
			VALUES (:submissionReturnId, :edumateTaskId, :categoryCode, :studentId, :updatedAt)
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':submissionReturnId', $submissionReturn->getId());
		$statement->bindParam(':edumateTaskId', $edumateMark->getTaskId());
		$statement->bindParam(':categoryCode', $edumateMark->getClassCode());
		$statement->bindParam(':studentId', $edumateMark->getStudentId());
		$statement->bindParam(':updatedAt', $edumateMark->getLastUpdatedDate());
		$statement->execute();
	}

	/**
	 * Create a relation between a SubmissionBox and an Edumate Task
	 *
	 * @param $submissionReturn
	 * @param \External\Edumate\Import\EdumateMark $edumateMark
	 */
	public function updateReturnRelation($submissionReturn, \External\Edumate\Import\EdumateMark $edumateMark) {
		$query = "
			UPDATE submission_return_edumate_task
			SET updated_at = :updatedAt
			WHERE submission_return_id = :submissionReturnId
			AND task_id = :edumateTaskId
			AND category_code = :categoryCode
			AND student_id = :studentId
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam(':submissionReturnId', $submissionReturn->getId());
		$statement->bindParam(':edumateTaskId', $edumateMark->getTaskId());
		$statement->bindParam(':categoryCode', $edumateMark->getClassCode());
		$statement->bindParam(':studentId', $edumateMark->getStudentId());
		$statement->bindParam(':updatedAt', $edumateMark->getLastUpdatedDate());
		$statement->execute();
	}
}