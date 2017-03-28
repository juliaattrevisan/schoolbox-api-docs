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

class External_Synergetic_Import_Task {

	/**
	 * @var MSSQL
	 */
	private $database;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->setDatabase(MSSQL::getInstance());
	}

	/**
	 * The file_type associated specially with SubmissionBoxes
	 * imported directly from Synergetic
	 *
	 * @var string
	 */
	private $taskKey = 'Task from Synergetic';

	/**
	 * Import all Synergetic tasks as Submission Boxes
	 */
	public function import() {
		$taskMapper = new External_Synergetic_Import_TaskMapper();
		$categoryMapper = new \Schoolbox\Resource\Folder\Mapper();
		$workTypeMapper = new \Schoolbox\Learning\Assessment\SubmissionType\Mapper();
		$markTypeMapper = new \Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper();
		$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
		$userMapper = new \Schoolbox\UserMapper();

		$query = "SELECT * uvSchoolboxTasks";

		$rows = $this->getDatabase()->getAll($query);

		/* Only create new Submission Boxes for tasks who don't already
		 * have submission boxes
		 */
		foreach ($rows as $row) {

			$synergeticTask = new \External\Synergetic\Import\SynergeticTask($row);

			$exists = $taskMapper->isTaskBoxExists($synergeticTask);

			/* Create a new one since it doesn't exist
			 */
			if (!$exists) {
				$submissionBox = new \Schoolbox\Assessment\Component\SubmissionBox();
			}
			else {
				/* Reuse an existing Submission Box
				 */
				$submissionBoxId = $taskMapper->findBoxFromTask($synergeticTask);
				try{
					$submissionBox = $submissionBoxMapper->findById($submissionBoxId);
				} catch(Exception $e) {
					echo "-  Skipping {$synergeticTask->getTaskId()} {$synergeticTask->getTask()}: nothing to update \n";
					continue;
				}
			}

			/* Assign category if it exists in schoolbox
			 */
			try {
				$category = $categoryMapper->findByCode($synergeticTask->getClassCode());
				$submissionBox->setCategory($category);
			}
			catch (\Exception $e) {

				/* Ignore everything if you can't find a Schoolbox Category
				 */
				echo "!  Skipping {$synergeticTask->getTaskId()} {$synergeticTask->getTask()}: cannot find Category with code {$synergeticTask->getClassCode()}\n";
				continue;
			}

			/* Assign submission box owner
			 */
			try {
				$teacher = $userMapper->findByColumn('synergy_id', $synergeticTask->getStaffNumber())->first();
				$submissionBox->setOwner($teacher);
			}
			catch (\Exception $e) {

				/* Ignore everything if you can't find a teacher
				 */
				echo "!  Skipping {$synergeticTask->getTaskId()} {$synergeticTask->getTask()}: cannot find Teacher with externalId {$synergeticTask->getStaffNumber()}\n";
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
					echo "-  Skipping {$synergeticTask->getTaskId()} {$synergeticTask->getTask()}: work Type not found \n";
					continue;
				}

				/* Assign name, description
				 */
				$submissionBox->setName($synergeticTask->getTask());
				$submissionBox->setDescription($synergeticTask->getDescription());

				/* Assign dates (assume publish date = open date)
				 */
				$submissionBox->setOpenDate(new \Alaress_Date_Format($synergeticTask->getSetDate(), 'Y-m-d'));
				$submissionBox->setPublishDate(new \Alaress_Date_Format($submissionBox->getOpenDate()));
				$submissionBox->setDueDate(new \Alaress_Date_Format($synergeticTask->getDueDate(), 'Y-m-d'));
				$submissionBox->setLateDate(new \Alaress_Date_Format($synergeticTask->getCloseDate(), 'Y-m-d'));
				$submissionBox->setCreated(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

				/* Assign submission acceptance settings
				 */
				$submissionBox->setAcceptOnline($synergeticTask->getAcceptSubmissions());
				$submissionBox->setAcceptMultiple(true);

				/* Assign marking rules
				 */
				$markType = $markTypeMapper->findByColumn('slug', 'raw');

				if ($markType->count()) {
					$submissionBox->setMarkType($markType->first());
				}
				else {
					echo "-  Skipping {$synergeticTask->getTaskId()} {$synergeticTask->getTask()}: Mark Type not found \n";
					continue;
				}

				$submissionBox->setWeight($synergeticTask->getWeighting());
				$submissionBox->setMaxMark($synergeticTask->getTotalMark());
				$submissionBox->setMarkVisibility(IAssessable::MARK_VISIBILITY_PRIVATE);

				/* Add this submission box to the tableau
				 */
				$submissionBoxMapper->save($submissionBox);

				/* Create a relation between the submission box and the Task
				 * from which it spawned
				 */
				if (!$exists) {
					$taskMapper->createBoxRelation($submissionBox, $synergeticTask);

					echo "+  Adding {$synergeticTask->getTaskId()} {$synergeticTask->getTask()}\n";
				}
			}
			else {
				echo "-  Skipping {$synergeticTask->getTaskId()} {$synergeticTask->getTask()}: Submission Box could not be instantiated\n";
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

	/**
	 * Set database
	 *
	 * @param $database
	 */
	private function setDatabase($database)
	{
		$this->database = $database;
	}

	/**
	 * Get database
	 *
	 * @return MSSQL
	 */
	private function getDatabase()
	{
		return $this->database;
	}
}
