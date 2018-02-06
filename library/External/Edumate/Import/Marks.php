<?php

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;

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
		$categoryMapper = new \Schoolbox\Resource\Folder\Mapper();
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
			$edumateMark = new \External\Edumate\Import\EdumateMark($row);

			/* Spawn a Return, or reuse an existing one that was imported
			 */
			$exists = $marksMapper->isTaskReturnExists($edumateMark);

			if (!$exists) {
				$submissionReturn = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork();

				$submissionReturn->setCreatedAt(new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			}
			else {
				/* Reuse an existing Submission Return
				 */
				if ($marksMapper->isNeedUpdate($edumateMark)) {
					$submissionReturnId = $marksMapper->findReturnFromTask($edumateMark);
					$submissionReturn = $submissionReturnMapper->findById($submissionReturnId);
				}
				else {
					/* Skip everything, because this Edumate Mark hasn't been updated
					 * since the last time we imported it
					 */
					echo "-  Skipping task {$edumateMark->getTaskId()} for student {$edumateMark->getStudentId()} in {$edumateMark->getClassCode()}: nothing to update \n";
					continue;
				}
			}

			/* Make sure the Submission Box exists first because we need to find the
			 * teacher, who will become the owner of the marks
			 */
			$edumateTask = new \External\Edumate\Import\EdumateTask();
			$edumateTask->setTaskId($edumateMark->getTaskId());
			$edumateTask->setClassCode($edumateMark->getClassCode());

			$submissionBoxId = $taskMapper->findBoxFromTask($edumateTask);

			try {
				$submissionBox = $submissionBoxMapper->findById($submissionBoxId);
				$submissionReturn->setSubmissionBox($submissionBox);
			}
			catch (\Exception $e) {
				echo "-  Skipping task {$edumateMark->getTaskId()} for student {$edumateMark->getStudentId()}: Submission Box for class code {$edumateMark->getClassCode()} not found \n";
				continue;
			}

			/* Assign submission return marker
			 */
			try {
				$category = $categoryMapper->findByCode($edumateMark->getClassCode());
			}
			catch (\Exception $e) {

				/* Ignore everything if you can't find a Schoolbox Category
				 */
				echo "!  Skipping {$edumateMark->getTaskId()} : cannot find Category with code {$edumateMark->getClassCode()}\n";
				continue;
			}

			$submissionReturn->setMarker($submissionBox->getOwner());
            $submissionReturn->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);

			/* Assign owner id
			 */
			$student = $userMapper->findByColumn('synergy_id', $edumateMark->getStudentId());

			if ($student->count()) {
				$submissionReturn->setOwner($student->first());
			}
			else {
				echo "-  Skipping task {$edumateMark->getTaskId()} for student {$edumateMark->getStudentId()} in {$edumateMark->getClassCode()}: Student not found \n";
				continue;
			}

			/* Assign marks and comments
			 */
			$submissionReturn->setComment('');
			$submissionReturn->setHumanReadableMark($edumateMark->getMark() . ' / ' . $submissionBox->getMaxMark());
			$submissionReturn->setNormalizedMark(100 * (float)$edumateMark->getMark()/(float)$submissionBox->getMaxMark());
//			$submissionReturn->setFilename(null);
//			$submissionReturn->setFilesize(null);
//			$submissionReturn->setMimetype(null);
			$submissionReturn->setCreatedAt(null);

			/* Save it
			 */
			$submissionReturnMapper->save($submissionReturn);

			/* Create relation
			 */
			if (!$exists) {
				$marksMapper->createReturnRelation($submissionReturn, $edumateMark);

				echo "+  Adding task {$edumateMark->getTaskId()} for student {$edumateMark->getStudentId()}\n";
			}
			else {
				$marksMapper->updateReturnRelation($submissionReturn, $edumateMark);

				echo "^  Updating task {$edumateMark->getTaskId()} for student {$edumateMark->getStudentId()}\n";
			}
		}

		echo "\n\n";
	}
}
