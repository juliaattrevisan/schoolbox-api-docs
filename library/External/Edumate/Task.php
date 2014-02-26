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
			if (!$taskMapper->isTaskExists($row['TASK_ID'])) {
				$submissionBox = new \Schoolbox\Assessment\Component\SubmissionBox();

				/* Assign category if it exists in schoolbox
				 */
				try {
					$category = $categoryMapper->findByCode($row['CLASS_CODE']);
					$submissionBox->setCategory($category);

					/* Assign submission box owner
					 */
					$category->loadTeachers();
					$owner = $category->getTeachers()->first();
					$submissionBox->setOwner($owner);
				}
				catch (\Exception $e) {
				}

				/* Assign work type
				 */
				try {
					$workType = $workTypeMapper->findByColumn('name', $row['TASK_TYPE']);
					$submissionBox->setWorkType($workType);
				}
				catch (\Exception $e) {
				}

				/* Assign name, description
				 */
				$submissionBox->setName($row['TASK']);
				$submissionBox->setDescription($row['DESCRIPTION']);

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
				try {
					$markType = $markTypeMapper->findByColumn('slug', 'raw');
					$submissionBox->setMarkType($markType);
				}
				catch (\Exception $e) {
				}

				$submissionBox->setWeight($row['WEIGHTING']);
				$submissionBox->setMaxMark($row['TOTAL_MARK']);
				$submissionBox->setMarkVisibility($submissionBox::MARK_VISIBILITY_PRIVATE);

				/* Add this submission box to the tableau
				 */
				$id = $submissionBoxMapper->save($submissionBox);

				/* Create a relation between the submission box and the Task
				 * from which it spawned
				 */
				new \Alaress_Probe($row);
			}
		}
	}
} 