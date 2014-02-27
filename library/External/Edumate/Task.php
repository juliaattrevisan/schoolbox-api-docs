<?php
/**
 * Tasks
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Edumate_Task extends External_Edumate_Connection {

	/**
	 * Import all Edumate tasks as Submission Boxes
	 */
	public function importAllTasks() {
		$taskMapper = new External_Edumate_TaskMapper();
		$categoryMapper = new \Schoolbox\Resource\CategoryMapper();
		$workTypeMapper = new \Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper();
		$markTypeMapper = new \Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper();
		$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();

		$query = "
			SELECT *
			FROM db2inst1.schoolbox_tasks
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->execute();

		/* Only create new Submission Boxes for tasks who don't already
		 * have submission boxes
		 */
		while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
			$exists = $taskMapper->isTaskExists($row['TASK_ID'], $row['CLASS_CODE']);

			/* Create a new one since it doesn't exist
			 */
			if (!$exists) {
				$submissionBox = new \Schoolbox\Assessment\Component\SubmissionBox();

				/* Alert user
 				 */
				echo "+  Adding {$row['TASK_ID']} {$row['TASK']}\n";
			}
			/* Reuse an existing Submission Box
			 */
			else {
				$submissionBoxId = $taskMapper->findSubmissionBoxFromTask($row['TASK_ID'], $row['CLASS_CODE']);
				$submissionBox = $submissionBoxMapper->findById($submissionBoxId);

				/* Alert user
 				 */
				echo "^  Updating {$row['TASK_ID']} {$row['TASK']}\n";
			}

			/* Assign category if it exists in schoolbox
			 */
			try {
				$category = $categoryMapper->findByCode($row['CLASS_CODE']);
				$submissionBox->setCategory($category);

				/* Assign submission box owner
				 */
				$category->loadTeachers();
				$submissionBox->setOwner($category->getTeachers()->first());
			}
			catch (\Exception $e) {

				/* Ignore everything if you can't find a Schoolbox Category
				 */
				echo "!  Cannot find Category with code {$row['CLASS_CODE']} for task {$row['TASK_ID']} {$row['TASK']}\n";
				continue;
			}

			/* Proceed if a Submission Box could be spawned
			 */
			if ($submissionBox instanceof \Schoolbox\Assessment\Component\SubmissionBox) {

				/* Assign work type
				 */
				$workType = $workTypeMapper->findByColumn('name', $row['TASK_TYPE']);

				if ($workType->count()) {
					$submissionBox->setWorkType($workType->first());
				}
				else {
					echo "-  Skipping {$row['TASK_ID']} {$row['TASK']} - Work Type not found \n";
					continue;
				}

				/* Assign name, description
				 */
				$submissionBox->setName($row['TASK']);
				$submissionBox->setDescription('' . $row['DESCRIPTION']);

				/* Assign dates
				 */
				$submissionBox->setOpenDate(new \Alaress_Date_Format($row['SET_DATE'], 'y-m-d'));
				$submissionBox->setDueDate(new \Alaress_Date_Format($row['DUE_DATE'], 'y-m-d'));
				$submissionBox->setLateDate(new \Alaress_Date_Format($row['CLOSE_DATE'], 'y-m-d'));
				$submissionBox->setCreated(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

				/* Assign submission acceptance settings
				 */
				$submissionBox->setAcceptOnline($row['ACCEPT_SUBMISSIONS']);
				$submissionBox->setAcceptMultiple(false);

				/* Assign marking rules
				 */
				$markType = $markTypeMapper->findByColumn('slug', 'raw');

				if ($markType->count()) {
					$submissionBox->setMarkType($markType->first());
				}
				else {
					echo "-  Skipping {$row['TASK_ID']} {$row['TASK']} - Mark Type not found \n";
					continue;
				}

				$submissionBox->setRubricResultSet(0);
				$submissionBox->setWeight($row['WEIGHTING']);
				$submissionBox->setMaxMark($row['TOTAL_MARK']);
				$submissionBox->setMarkVisibility($submissionBox::MARK_VISIBILITY_PRIVATE);

				/* Add this submission box to the tableau
				 */
				$submissionBoxMapper->save($submissionBox);

				/* Create a relation between the submission box and the Task
				 * from which it spawned
				 */
				if (!$exists) {
					$taskMapper->createRelation($submissionBox, $row['TASK_ID'], $row['CLASS_CODE']);
				}
			}
			else {
				echo "-  Skipping {$row['TASK_ID']} {$row['TASK']} - Submission Box could not be instantiated \n";
			}
		}

		echo "\n\n";
	}

	/**
	 * Import marks from Task/CategoryCode to SubmissionBox/Student combo
	 */
	public function importAllMarks() {
		$taskMapper = new External_Edumate_TaskMapper();
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
			$exists = $taskMapper->isTaskReturnExists($row['TASK_ID'], $row['CLASS_CODE']);

			if (!$exists) {
				$submissionReturn = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork();

				/* Alert user
 				 */
				echo "+  Adding {$row['TASK_ID']} for {$row['STUDENT_ID']}\n";
			}
			else {
				$submissionReturnId = $taskMapper->findSubmissionReturnFromTask($row['TASK_ID'], $row['CLASS_CODE']);
				$submissionReturn = $submissionReturnMapper->findById($submissionReturnId);

				/* Alert user
 				 */
				echo "^  Updating {$row['TASK_ID']} for {$row['STUDENT_ID']}\n";
			}

			/* Make sure the Submission Box exists first because we need to find the
			 * teacher, who will become the owner of the marks
			 */
			$submissionBoxId = $taskMapper->findSubmissionBoxFromTask($row['TASK_ID'], $row['CLASS_CODE']);

			try {
				$submissionBox = $submissionBoxMapper->findById($submissionBoxId);
				$submissionReturn->setSubmissionBox($submissionBox);
			}
			catch (\Exception $e) {
				echo "-  Skipping {$row['TASK_ID']} for {$row['STUDENT_ID']} - Submission Box {$submissionBox->getId()} not found \n";
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
				$taskMapper->createReturnRelation($submissionReturn, $row['TASK_ID'], $row['CLASS_CODE']);
			}
		}

		echo "\n\n";
	}
}