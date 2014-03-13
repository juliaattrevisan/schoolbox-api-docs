<?php
/**
 * Tasks
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Edumate_Import_Task extends External_Edumate_Connection {

	/**
	 * The file_type associated specially with SubmissionBoxes
	 * imported directly from Edumate
	 *
	 * @var string
	 */
	private $taskKey = 'Task from Edumate';

	/**
	 * Import all Edumate tasks as Submission Boxes
	 */
	public function import() {
		$taskMapper = new External_Edumate_Import_TaskMapper();
		$categoryMapper = new \Schoolbox\Resource\CategoryMapper();
		$workTypeMapper = new \Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper();
		$markTypeMapper = new \Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper();
		$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
		$userMapper = new \Schoolbox\UserMapper();

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
			$exists = $taskMapper->isTaskBoxExists($row['TASK_ID'], $row['CLASS_CODE']);

			/* Create a new one since it doesn't exist
			 */
			if (!$exists) {
				$submissionBox = new \Schoolbox\Assessment\Component\SubmissionBox();
			}
			else {
				/* Reuse an existing Submission Box
				 */
				if ($taskMapper->isNeedUpdate($row['TASK_ID'], $row['CLASS_CODE'], $row['LAST_UPDATED'])) {
					$submissionBoxId = $taskMapper->findBoxFromTask($row['TASK_ID'], $row['CLASS_CODE']);
					$submissionBox = $submissionBoxMapper->findById($submissionBoxId);
				}
				else {
					/* Skip everything, because this Edumate Task hasn't been updated
					 * since the last time we imported it
					 */
					echo "-  Skipping {$row['TASK_ID']} {$row['TASK']} - Nothing to update \n";
					continue;
				}
			}

			/* Assign category if it exists in schoolbox
			 */
			try {
				$category = $categoryMapper->findByCode($row['CLASS_CODE']);
				$submissionBox->setCategory($category);
			}
			catch (\Exception $e) {

				/* Ignore everything if you can't find a Schoolbox Category
				 */
				echo "!  Cannot find Category with code {$row['CLASS_CODE']} for task {$row['TASK_ID']} {$row['TASK']}\n";
				continue;
			}

			/* Assign submission box owner
			 */
			try {
				$teacher = $userMapper->findByColumn('synergy_id', $row['STAFF_NUMBER'])->first();
				$submissionBox->setOwner($teacher);
			}
			catch (\Exception $e) {

				/* Ignore everything if you can't find a teacher
				 */
				echo "!  Cannot find Teacher with externalId {$row['STAFF_NUMBER']} for task {$row['TASK_ID']} {$row['TASK']}\n";
				continue;
			}

			/* Proceed if a Submission Box could be spawned
			 */
			if ($submissionBox instanceof \Schoolbox\Assessment\Component\SubmissionBox) {

				/* Assign work type
				 */
				$workType = $workTypeMapper->findByColumn('name', $this->getTaskKey());

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
					$taskMapper->createBoxRelation($submissionBox, $row['TASK_ID'], $row['CLASS_CODE'], $row['LAST_UPDATED']);

					echo "+  Adding {$row['TASK_ID']} {$row['TASK']}\n";
				}
				else {
					$taskMapper->updateBoxRelation($submissionBox, $row['TASK_ID'], $row['CLASS_CODE'], $row['LAST_UPDATED']);
					echo "^  Updating {$row['TASK_ID']} {$row['TASK']}\n";
				}
			}
			else {
				echo "-  Skipping {$row['TASK_ID']} {$row['TASK']} - Submission Box could not be instantiated \n";
			}
		}

		echo "\n\n";
	}

	/* Getters/Setters
	 */

	/**
	 * Set task key
	 *
	 * @param string $taskKey
	 */
	private function setTaskKey($taskKey) {
		$this->taskKey = $taskKey;
	}

	/**
	 * Get task key
	 *
	 * @return string
	 */
	private function getTaskKey() {
		return $this->taskKey;
	}
}