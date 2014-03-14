<?php
/**
 * Tasks
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Edumate_Import_Marks extends External_Edumate_Connection {

	/**
	 * Import marks from Task/CategoryCode to SubmissionBox/Student combo
	 */
	public function import() {
		$taskMapper = new External_Edumate_Import_TaskMapper();
		$marksMapper = new External_Edumate_Import_MarksMapper();
		$categoryMapper = new \Schoolbox\Resource\CategoryMapper();
		$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
		$submissionReturnMapper = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();
		$userMapper = new \Schoolbox\UserMapper();

		$query = "
			SELECT *
			FROM db2inst1.schoolbox_task_marks
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->execute();

		/* Only create new Submission Boxes for tasks who don't already
		 * have submission boxes
		 */
		while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

			/* Spawn a Return, or reuse an existing one that was imported
			 */
			$exists = $marksMapper->isTaskReturnExists($row['TASK_ID'], $row['CLASS_CODE'], $row['STUDENT_ID']);

			if (!$exists) {
				$submissionReturn = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork();

				$submissionReturn->setReturned(new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			}
			else {
				/* Reuse an existing Submission Return
				 */
				if ($marksMapper->isNeedUpdate($row['TASK_ID'], $row['CLASS_CODE'], $row['STUDENT_ID'], $row['LAST_UPDATED'])) {
					$submissionReturnId = $marksMapper->findReturnFromTask($row['TASK_ID'], $row['CLASS_CODE'], $row['STUDENT_ID']);
					$submissionReturn = $submissionReturnMapper->findById($submissionReturnId);
				}
				else {
					/* Skip everything, because this Edumate Mark hasn't been updated
					 * since the last time we imported it
					 */
					echo "-  Skipping task {$row['TASK_ID']} for student {$row['STUDENT_ID']} in {$row['CLASS_CODE']}: nothing to update \n";
					continue;
				}
			}

			/* Make sure the Submission Box exists first because we need to find the
			 * teacher, who will become the owner of the marks
			 */
			$submissionBoxId = $taskMapper->findBoxFromTask($row['TASK_ID'], $row['CLASS_CODE']);

			try {
				$submissionBox = $submissionBoxMapper->findById($submissionBoxId);
				$submissionReturn->setSubmissionBox($submissionBox);
			}
			catch (\Exception $e) {
				echo "-  Skipping task {$row['TASK_ID']} for student {$row['STUDENT_ID']}: Submission Box for class code {$row['CLASS_CODE']} not found \n";
				continue;
			}

			/* Assign submission return marker
			 */
			try {
				$category = $categoryMapper->findByCode($row['CLASS_CODE']);
			}
			catch (\Exception $e) {

				/* Ignore everything if you can't find a Schoolbox Category
				 */
				echo "!  Skipping {$row['TASK_ID']} {$row['TASK']}: cannot find Category with code {$row['CLASS_CODE']}\n";
				continue;
			}

			$submissionReturn->setMarker($submissionBox->getOwner());

			/* Assign owner id
			 */
			$student = $userMapper->findByColumn('synergy_id', $row['STUDENT_ID']);

			if ($student->count()) {
				$submissionReturn->setOwner($student->first());
			}
			else {
				echo "-  Skipping task {$row['TASK_ID']} for student {$row['STUDENT_ID']} in {$row['CLASS_CODE']}: Student not found \n";
				continue;
			}

			/* Assign marks and comments
			 */
			$submissionReturn->setComment('');
			$submissionReturn->setMark($row['MARK'] . ' / ' . $submissionBox->getMaxMark());
			$submissionReturn->setNormalizedMark(100 * (float)$row['MARK']/(float)$submissionBox->getMaxMark());
			$submissionReturn->setFilename(null);
			$submissionReturn->setFilesize(null);
			$submissionReturn->setMimetype(null);
			$submissionReturn->setDeleted(0);
			$submissionReturn->setSubmitted(null);

			/* Save it
			 */
			$submissionReturnMapper->save($submissionReturn);

			/* Create relation
			 */
			if (!$exists) {
				$marksMapper->createReturnRelation($submissionReturn, $row['TASK_ID'], $row['CLASS_CODE'], $row['STUDENT_ID'], $row['LAST_UPDATED']);

				echo "+  Adding task {$row['TASK_ID']} for student {$row['STUDENT_ID']}\n";
			}
			else {
				$marksMapper->updateReturnRelation($submissionReturn, $row['TASK_ID'], $row['CLASS_CODE'], $row['STUDENT_ID'], $row['LAST_UPDATED']);

				echo "^  Updating task {$row['TASK_ID']} for student {$row['STUDENT_ID']}\n";
			}
		}

		echo "\n\n";
	}
}