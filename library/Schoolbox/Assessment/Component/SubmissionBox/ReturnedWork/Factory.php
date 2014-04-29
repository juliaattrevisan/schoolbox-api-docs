<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;


use Schoolbox\Assessment\Component\SubmissionBox;

class Factory {

	/**
	 * A schoolbox user
	 *
	 * @var null
	 */
	private $user = null;

	/**
	 * An instance of Submission
	 *
	 * @var null
	 */
	private $submissionBox = null;

	/**
	 * The mark associated with the returned work
	 * @var string
	 */
	private $mark = '';

	/**
	 * The comment associated with the returned work
	 *
	 * @var string
	 */
	private $comment = '';

	/**
	 * An array of rubric values selected by the marker
	 *
	 * @var array
	 */
	private $rubric = array();

	/**
	 * A $_FILES array element
	 *
	 * @var null
	 */
	private $file = array();

	/**
	 * An instance of \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper
	 *
	 * @var null
	 */
	private $returnedWorkMapper = null;

	/**
	 * A thing that came out of Marksheet_Factory
	 *
	 * @var null
	 */
	private $marksheet = null;

	/**
	 * The time differential between using an old ReturnedWork record and spawning
	 * a whole new one
	 */
	private $updateWindow = 21600; // 60 * 60 * 6 seconds = 6 hours

	/**
	 * A list of 'errors' which help translate non-numeric values for
	 * Absent, Submitted, Not Submitted etc. into numeric ones
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Constructor
	 *
	 * $user is the person who submitted the item for marking
	 * and is also synonymous with 'owner' column in the db
	 *
	 * @param \Schoolbox\User $user
	 * @param \Schoolbox\Assessment\Component\SubmissionBox $submissionBox
	 * @param $mark
	 * @param $comment
	 * @param $rubric
	 * @param $file
	 */
	public function __construct(\Schoolbox\User $user, \Schoolbox\Assessment\Component\SubmissionBox $submissionBox, $mark, $comment, $rubric, $file) {
		$this->setUser($user);
		$this->setSubmissionBox($submissionBox);
		$this->setMark($mark);
		$this->setComment($comment);
		$this->setRubric($rubric);
		$this->setFile($file);

		/* Internally used instances
		 */
		$this->setReturnedWorkMapper(new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper());
		$this->setMarksheet(\Marksheet_Factory::build($submissionBox->getMarkType()->getSlug()));

		/* Sometimes you don't submit numeric values - this mainly consists
		 * of possibilities like Absent, Not Submitted, etc. These cause an error
		 * due to an assumed number formatting in Marksheet/Abstract
		 *
		 * This translates the non-numeric values into numeric ones
		 *
		 * Adapted from the old returnWork.php in ajaxUpdate
		 */
		$this->setErrors($this->detectErrors());
	}

	/**
	 * This actually translates string Mark values into numeric ones, despite the name.
	 * This occurs because MarkSheets all use normaliseMark, assuming a numeric value.
	 * So you use DueWork_Errors to translate
	 *
	 * @return DueWork_Error[]
	 */
	private function detectErrors() {

		// retrieve error codes
		$allErrors = \DueWork_ErrorMapper::getInstance()->findAll();

		// make associative by label (since that's what's being transmitted)
		/* @var DueWork_Error[] $errors */
		$errors = array();

		foreach($allErrors as $currentError) {
			/* @var $e DueWork_Error */
			$errors[$currentError->getDisplayAs()] = $currentError;
		}

		return $errors;
	}

	/**
	 * Start processing the Returned Work
	 */
	public function start() {

		/* Figure out which returned work record to use
		 * Assign marks, normalised marks, ownerships etc
		 */
		$returnedWork = $this->processReturnedWork();

		/* If there was a file upload, attach it
		 */
		$returnedWork = $this->addFile($returnedWork);

		/* Ram it home
		 */
		try {
			$this->getReturnedWorkMapper()->save($returnedWork);

			// Rubrics have no mappers yet, so handle them the old-fashioned way.
			if (is_array($this->getRubric())) {
				$rubric_handler = new \DueWork_Rubric_Handler();
				$rubric_handler->saveMarks($this->getRubric(), $this->getUser()->getId(), $this->getSubmissionBox()->getId(), $returnedWork->getReturned()->getDatabase());

				/* wat...?
				 *
				 * What is the point of this? Why isn't it just the same as the $returnedWork->getReturned()
				 * value (that is, the timestamp when the Returned Work was last modified)
				 *
				 * Why would you want to preserve the timestamp for when this rubric_mark value
				 * was first created?
				 *
					$stamp = null;
					if ($update_existing) {
						$stamp = $rubric_handler->getLatestMarkTimestamp($student_id, $box_id);
					}
					$rubric_handler->saveMarks($rubric, $student_id, $box_id, $stamp);
				 */
			}
		}
		catch(\Exception $e) {
		}

		return $returnedWork;
	}

	/**
	 * Get the returned work record to deal with
	 * This either spawns a new one, or uses an old one from some time ago
	 *
	 * @return \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork
	 */
	private function retrieveReturnedWorkRecord() {
		$returnedWork = null;

		/* If you can't find an existing record, spawn a new one
		 */
		try {
			/** @var ReturnedWork $returned_work */
			$returnedWork = $this->getReturnedWorkMapper()->findOneByColumnList(
				array(
					'owner' => $this->getUser()->getId(),
					'box_id' => $this->getSubmissionBox()->getId(),
				),
				array(
					'returned' => 'desc'
				)
			);

			/* Resume modification of an existing returned work record that was
			 * found a few moments ago, rather than spawn a new one
			 *
			 * This fixes the scenario where marks were entered into one record
			 * and the comments (added shortly thereafter) were put into a new
			 * record unintentionally
			 */
			// Is this outside of the update window? if so, create a new one.
			$return_time = $returnedWork->getReturned();
			$window_ago  = new \Alaress_Date_Format(time() - $this->getUpdateWindow(), 'U');

			/* Use the existing returned work record - it was created a short time ago or the marks are not yet visible
			 */
			if ($this->getSubmissionBox()->getMarkVisibility() == SubmissionBox::MARK_VISIBILITY_PRIVATE
				|| $return_time->getUnixTimestamp() >= $window_ago->getUnixTimestamp())
			{
				$returnedWork->setReturned(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			}
			/* Spawn a new returned work record, so pump in a returned date
			 * and all the ownership stuff
			 */
			else {
				$returnedWork = $this->spawnNewReturnedWork();
			}
		}
		/* Spawn a new one with the appropriate ownerships when returning work
		 * for the fiiiiirrst tiiiimee
		 */
		catch (\Schoolbox\Mapper\Exception $e) {
			$returnedWork = $this->spawnNewReturnedWork();
		}

		return $returnedWork;
	}

	/**
	 * Spawn a new returned work record with appropriate ownerships
	 *
	 * @return \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork
	 */
	private function spawnNewReturnedWork() {
		$returnedWork = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork();
		$returnedWork->setOwner($this->getUser());
		$returnedWork->setSubmissionBox($this->getSubmissionBox());
		$returnedWork->setReturned(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

		return $returnedWork;
	}

	/**
	 * Apply updates to the returned work record
	 *
	 * @return \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork
	 */
	private function processReturnedWork() {
		$returnedWork = $this->retrieveReturnedWorkRecord();


		/* A string mark was submitted (Absent, Not Submitted, etc)
		 */
		if (isset($errors[$this->getMark()])) {
			$returnedWork->setMark($errors[$this->getMark()]->getDisplayAs());
			$returnedWork->setNormalisedMark($errors[$this->getMark()]->getNormaliseAs());
		}
		/* If it's a rubric, it normalises in a special way
		 */
		else if ($this->getSubmissionBox()->isRubric()) {

			$normalisedMarkArray = $this->getMarksheet()->normaliseMark($this->getRubric(), $this->getSubmissionBox());

			$returnedWork->setMark($normalisedMarkArray['mark']);
			$returnedWork->setNormalizedMark($normalisedMarkArray['norm_mark']);

		}
        /* Empty mark (not zero) - treat as null*/
        else if ((trim($this->getMark()) === '') || ($this->getMark() < 0)) {
            $returnedWork->setMark(null);
            $returnedWork->setNormalizedMark(null);
        }
		/* Just do it in the normal way
		 */
		else {
			$normalisedMarkArray = $this->getMarksheet()->normaliseMark($this->getMark(), $this->getSubmissionBox());

			$returnedWork->setMark($this->getMark());
			$returnedWork->setNormalizedMark($normalisedMarkArray['norm_mark']);
		}

		/* Update the existing Returned Work record with new data
		 */
		$returnedWork->setComment($this->getComment());
		$returnedWork->setMarker($_SESSION['user']->getId());

		return $returnedWork;
	}

	/**
	 * Add an optional file
	 *
	 * @param $returnedWork
	 * @return mixed
	 */
	private function addFile($returnedWork) {
		$file = $this->getFile();

		if (is_array($file) && strlen($file['name'])) {
			$allowedFileHandler = new \AllowedFile();
			$allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($file));

			/* Save filesize in db
			 */
			$returnedWork->setFilesize($file['size']);
			$returnedWork->setMimeType($file['type']);

			$returnedWork
				->setFilename($file['name'])
				->setTempLocation($file['tmp_name'])
				->loadFilesizeFromTempLocation();
		}

		return $returnedWork;
	}

	/* Getters/Setters
	 */

	/**
	 * Set comment
	 *
	 * @param string $comment
	 */
	private function setComment($comment) {
		$this->comment = $comment;
	}

	/**
	 * Get comment
	 *
	 * @return string
	 */
	private function getComment() {
		return $this->comment;
	}

	/**
	 * Set mark
	 *
	 * @param string $mark
	 */
	private function setMark($mark) {
		$this->mark = $mark;
	}

	/**
	 * Get mark
	 *
	 * @return string
	 */
	private function getMark() {
		return $this->mark;
	}

	/**
	 * Set submission box
	 *
	 * @param null $submissionBox
	 */
	private function setSubmissionBox($submissionBox) {
		$this->submissionBox = $submissionBox;
	}

	/**
	 * Get submission box
	 *
	 * @return null
	 */
	private function getSubmissionBox() {
		return $this->submissionBox;
	}

	/**
	 * Set user
	 *
	 * @param null $user
	 */
	private function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return null
	 */
	private function getUser() {
		return $this->user;
	}

	/**
	 * Set file
	 *
	 * @param null $file
	 */
	private function setFile($file) {
		$this->file = $file;
	}

	/**
	 * Get file
	 *
	 * @return null
	 */
	private function getFile() {
		return $this->file;
	}

	/**
	 * Set returned work mapper
	 *
	 * @param null $returnedWorkMapper
	 */
	private function setReturnedWorkMapper($returnedWorkMapper) {
		$this->returnedWorkMapper = $returnedWorkMapper;
	}

	/**
	 * Get returned work mapper
	 *
	 * @return null
	 */
	private function getReturnedWorkMapper() {
		return $this->returnedWorkMapper;
	}

	/**
	 * Set marksheet
	 *
	 * @param null $marksheet
	 */
	private function setMarksheet($marksheet) {
		$this->marksheet = $marksheet;
	}

	/**
	 * Get marksheet
	 *
	 * @return null
	 */
	private function getMarksheet() {
		return $this->marksheet;
	}

	/**
	 * Set update window
	 *
	 * @param mixed $updateWindow
	 */
	private function setUpdateWindow($updateWindow) {
		$this->updateWindow = $updateWindow;
	}

	/**
	 * Get update window
	 *
	 * @return mixed
	 */
	private function getUpdateWindow() {
		return $this->updateWindow;
	}

	/**
	 * Set rubric
	 *
	 * @param array $rubric
	 */
	private function setRubric($rubric) {
		$this->rubric = $rubric;
	}

	/**
	 * Get rubric
	 *
	 * @return array
	 */
	private function getRubric() {
		return $this->rubric;
	}

	/**
	 * Set errors
	 *
	 * @param array $errors
	 */
	private function setErrors($errors) {
		$this->errors = $errors;
	}

	/**
	 * Get errors
	 *
	 * @return array
	 */
	private function getErrors() {
		return $this->errors;
	}
}