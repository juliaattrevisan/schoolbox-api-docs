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
	 * Find out if an Edumate Task already exists as a submission box
	 *
	 * @param $edumateTaskId
	 * @return bool
	 */
	public function isTaskExists($edumateTaskId) {
		$query = "
			SELECT COUNT(*) AS num
			FROM submission_box_edumate_task
			WHERE task_id = :edumateTaskId
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->bindParam('edumateTaskId', $edumateTaskId);
		$statement->execute();

		$row = $statement->fetch();

		if ($row !== false) {
			if ($row['num'] > 0) {
				return true;
			}
		}

		return false;
	}

	public function createRelation($submissionBox, $edumateTaskId) {
		$query = "";
	}

}