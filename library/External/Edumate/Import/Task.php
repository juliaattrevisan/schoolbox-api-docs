<?php
/**
 * Tasks
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\IAssessable;

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
		$categoryMapper = new \Schoolbox\Resource\Folder\Mapper();
		$workTypeMapper = new \Schoolbox\Learning\Assessment\SubmissionType\Mapper();
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
			$edumateTask = new \External\Edumate\Import\EdumateTask($row);

			$exists = $taskMapper->isTaskBoxExists($edumateTask);

			/* Create a new one since it doesn't exist
			 */
			if (!$exists) {
				$submissionBox = new \Schoolbox\Assessment\Component\SubmissionBox();
			}
			else {
				/* Reuse an existing Submission Box
				 */
				if ($taskMapper->isNeedUpdate($edumateTask)) {
					$submissionBoxId = $taskMapper->findBoxFromTask($edumateTask);
                    try{
					    $submissionBox = $submissionBoxMapper->findById($submissionBoxId);
                    } catch(Exception $e) {
                        echo "-  Skipping {$edumateTask->getTaskId()} {$edumateTask->getTask()}: nothing to update \n";
                        continue;
                    }
				}
				else {
					/* Skip everything, because this Edumate Task hasn't been updated
					 * since the last time we imported it
					 */
					echo "-  Skipping {$edumateTask->getTaskId()} {$edumateTask->getTask()}: nothing to update \n";
					continue;
				}
			}

			/* Assign category if it exists in schoolbox
			 */
			try {
				$category = $categoryMapper->findByCode($edumateTask->getClassCode());
				$submissionBox->setCategory($category);
			}
			catch (\Exception $e) {

				/* Ignore everything if you can't find a Schoolbox Category
				 */
				echo "!  Skipping {$edumateTask->getTaskId()} {$edumateTask->getTask()}: cannot find Category with code {$edumateTask->getClassCode()}\n";
				continue;
			}

			/* Assign submission box owner
			 */
			try {
				$teacher = $userMapper->findByColumn('synergy_id', $edumateTask->getStaffNumber())->first();
				$submissionBox->setOwner($teacher);
			}
			catch (\Exception $e) {

				/* Ignore everything if you can't find a teacher
				 */
				echo "!  Skipping {$edumateTask->getTaskId()} {$edumateTask->getTask()}: cannot find Teacher with externalId {$edumateTask->getStaffNumber()}\n";
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
					echo "-  Skipping {$edumateTask->getTaskId()} {$edumateTask->getTask()}: work Type not found \n";
					continue;
				}

				/* Assign name, description
				 */
				$submissionBox->setName($edumateTask->getTask());
				$submissionBox->setDescription($edumateTask->getDescription());

				/* Assign dates (assume publish date = open date)
				 */
				$submissionBox->setOpenDate(new \Alaress_Date_Format($edumateTask->getSetDate(), 'Y-m-d'));
				$submissionBox->setPublishDate(new \Alaress_Date_Format($submissionBox->getOpenDate()));
				$submissionBox->setDueDate(new \Alaress_Date_Format($edumateTask->getDueDate(), 'Y-m-d'));
				$submissionBox->setLateDate(new \Alaress_Date_Format($edumateTask->getCloseDate(), 'Y-m-d'));
				$submissionBox->setCreated(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

				/* Assign submission acceptance settings
				 */
				$submissionBox->setAcceptOnline($edumateTask->getAcceptSubmissions());
				$submissionBox->setAcceptMultiple(true);

				/* Assign marking rules
				 */
				$markType = $markTypeMapper->findByColumn('slug', 'raw');

				if ($markType->count()) {
					$submissionBox->setMarkType($markType->first());
				}
				else {
					echo "-  Skipping {$edumateTask->getTaskId()} {$edumateTask->getTask()}: Mark Type not found \n";
					continue;
				}

				$submissionBox->setWeight($edumateTask->getWeighting());
				$submissionBox->setMaxMark($edumateTask->getTotalMark());
				$submissionBox->setMarkVisibility(IAssessable::MARK_VISIBILITY_PRIVATE);

				/* Add this submission box to the tableau
				 */
				$submissionBoxMapper->save($submissionBox);

				/* Create a relation between the submission box and the Task
				 * from which it spawned
				 */
				if (!$exists) {
					$taskMapper->createBoxRelation($submissionBox, $edumateTask);

					echo "+  Adding {$edumateTask->getTaskId()} {$edumateTask->getTask()}\n";
				}
				else {
					$taskMapper->updateBoxRelation($submissionBox, $edumateTask);
					echo "^  Updating {$edumateTask->getTaskId()} {$edumateTask->getTask()}\n";
				}
			}
			else {
				echo "-  Skipping {$edumateTask->getTaskId()} {$edumateTask->getTask()}: Submission Box could not be instantiated\n";
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
