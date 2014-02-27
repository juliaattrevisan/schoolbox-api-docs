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

			/* TODO: Import from their result set
			 */
			$row['CLASS_CODE'] = 'temp';

			/* Spawn a Return, or reuse an existing one that was imported
			 */
			$exists = $marksMapper->isTaskReturnExists($row['TASK_ID'], $row['CLASS_CODE']);

			if (!$exists) {
				$submissionReturn = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork();
			}
			else {
				$submissionReturnId = $marksMapper->findReturnFromTask($row['TASK_ID'], $row['CLASS_CODE']);
				$submissionReturn = $submissionReturnMapper->findById($submissionReturnId);
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
				echo "-  Skipping {$row['TASK_ID']} for {$row['STUDENT_ID']} - Submission Box for class code {$row['CLASS_CODE']} not found \n";
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
				echo "!  Cannot find Category with code {$row['CLASS_CODE']} for task {$row['TASK_ID']} {$row['TASK']}\n";
				continue;
			}

			$category->loadTeachers();
			$submissionReturn->setMarker($category->getTeachers()->first());

			/* Assign owner id
			 */
			$student = $userMapper->findByColumn('synergy_id', $row['STUDENT_ID']);

			if ($student->count()) {
				$submissionReturn->setOwner($student->first());
			}
			else {
				echo "-  Skipping {$row['TASK_ID']} for {$row['STUDENT_ID']} - Student not found \n";
				continue;
			}

			/* Assign marks and comments
			 */
			$submissionReturn->setComment('');
			$submissionReturn->setMark($row['MARK'] . ' / ' . $submissionBox->getMaxMark());
			$submissionReturn->setNormalizedMark($row['MARK']);
			$submissionReturn->setFilename(null);
			$submissionReturn->setFilesize(null);
			$submissionReturn->setMimetype(null);
			$submissionReturn->setDeleted(null);
			$submissionReturn->setSubmitted(null);
			$submissionReturn->setReturned(new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

			/* Save it
			 */
			$submissionReturnMapper->save($submissionReturn);

			/* Create relation
			 */
			if (!$exists) {
				$marksMapper->createReturnRelation($submissionReturn, $row['TASK_ID'], $row['CLASS_CODE']);

				echo "+  Adding {$row['TASK_ID']} for {$row['STUDENT_ID']}\n";
			}
			else {
				echo "^  Updating {$row['TASK_ID']} for {$row['STUDENT_ID']}\n";
			}
		}

		echo "\n\n";
	}
}