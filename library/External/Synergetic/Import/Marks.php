<?php
/**
 * Tasks
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Synergetic_Import_Marks {

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
	 * Import marks from Task/CategoryCode to SubmissionBox/Student combo
	 */
	public function import() {
		$taskMapper = new External_Synergetic_Import_TaskMapper();
		$marksMapper = new External_Synergetic_Import_MarksMapper();
		$categoryMapper = new \Schoolbox\Resource\Folder\Mapper();
		$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
		$submissionReturnMapper = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();
		$userMapper = new \Schoolbox\UserMapper();

		$query = "SELECT * FROM uvSchoolboxTaskResults";

		$rows = $this->getDatabase()->getAll($query);

		/* Only create new Submission Boxes for tasks who don't already
		 * have submission boxes
		 */
		foreach ($rows as $row) {
			$synergeticMark = new \External\Synergetic\Import\SynergeticMark($row);

			/* Spawn a Return, or reuse an existing one that was imported
			 */
			$exists = $marksMapper->isTaskReturnExists($synergeticMark);

			if (!$exists) {
				$submissionReturn = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork();

				$submissionReturn->setCreatedAt(new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			}
			else {
				/* Reuse an existing Submission Return
				 */
				$submissionReturnId = $marksMapper->findReturnFromTask($synergeticMark);
				$submissionReturn = $submissionReturnMapper->findById($submissionReturnId);
			}

			/* Make sure the Submission Box exists first because we need to find the
			 * teacher, who will become the owner of the marks
			 */
			$synergeticTask = new \External\Synergetic\Import\SynergeticTask();
			$synergeticTask->setTaskId($synergeticMark->getTaskId());
			$synergeticTask->setClassCode($synergeticMark->getClassCode());

			$submissionBoxId = $taskMapper->findBoxFromTask($synergeticTask);

			try {
				$submissionBox = $submissionBoxMapper->findById($submissionBoxId);
				$submissionReturn->setSubmissionBox($submissionBox);
			}
			catch (\Exception $e) {
				echo "-  Skipping task {$synergeticMark->getTaskId()} for student {$synergeticMark->getStudentId()}: Submission Box for class code {$synergeticMark->getClassCode()} not found \n";
				continue;
			}

			/* Assign submission return marker
			 */
			try {
				$category = $categoryMapper->findByCode($synergeticMark->getClassCode());
			}
			catch (\Exception $e) {

				/* Ignore everything if you can't find a Schoolbox Category
				 */
				echo "!  Skipping {$synergeticMark->getTaskId()} : cannot find Category with code {$synergeticMark->getClassCode()}\n";
				continue;
			}

			$submissionReturn->setMarker($submissionBox->getOwner());

			/* Assign owner id
			 */
			$student = $userMapper->findByColumn('synergy_id', $synergeticMark->getStudentId());

			if ($student->count()) {
				$submissionReturn->setOwner($student->first());
			}
			else {
				echo "-  Skipping task {$synergeticMark->getTaskId()} for student {$synergeticMark->getStudentId()} in {$synergeticMark->getClassCode()}: Student not found \n";
				continue;
			}

			/* Assign marks and comments
			 */
			$submissionReturn->setComment('');
			$submissionReturn->setHumanReadableMark($synergeticMark->getMark() . ' / ' . $submissionBox->getMaxMark());
			$submissionReturn->setNormalizedMark(100 * (float)$synergeticMark->getMark()/(float)$submissionBox->getMaxMark());
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
				$marksMapper->createReturnRelation($submissionReturn, $synergeticMark);

				echo "+  Adding task {$synergeticMark->getTaskId()} for student {$synergeticMark->getStudentId()}\n";
			}
		}

		echo "\n\n";
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