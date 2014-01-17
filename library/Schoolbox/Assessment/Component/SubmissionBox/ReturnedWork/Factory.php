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
	 * A url to visit after processing everything
	 *
	 * @var string
	 */
	private $redirect = '';

	/**
	 * An instance of \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper
	 *
	 * @var null
	 */
	private $returnedWorKMapper = null;

	/**
	 * A thing that came out of Marksheet_Factory
	 *
	 * @var null
	 */
	private $marksheet = null;

	/**
	 * TODO: refactor this to somwhere else, not sure where it should live though
	 * IT NOW LIVES HERE
	 */
	private $updateWindow = 21600; // 60 * 60 * 6 seconds = 6 hours

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
	 * @param $redirect
	 */
	public function __construct(\Schoolbox\User $user, \Schoolbox\Assessment\Component\SubmissionBox $submissionBox, $mark, $comment, $rubric, $file, $redirect) {
		$this->setUser($user);
		$this->setSubmissionBox($submissionBox);
		$this->setMark($mark);
		$this->setComment($comment);
		$this->setRubric($rubric);
		$this->setFile($file);
		$this->setRedirect($redirect);

		/* Internally used instances
		 */
		$this->setReturnedWorkMapper(new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper());

		$this->setMarksheet(\Marksheet_Factory::build($submissionBox->getMarkType()->getSlug()));
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
			echo $e->getMessage();
		}

//			$saveReturn = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkSaver();
//			$saveReturn->saveReturn($returnedWork, null, $file);
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

			/* This is a new returned work record, so pump in a returned date
			 * otherwise leave it alone, since it's being updated for comments/marks
			 */
			if ($return_time->getUnixTimestamp() >= $window_ago->getUnixTimestamp()) {
				$returnedWork->setReturned(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			}
		}
		/* Spawn a new one with the appropriate ownerships
		 */
		catch (\Schoolbox\Mapper\Exception $e) {
			$returnedWork = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork();
			$returnedWork->setOwner($this->getUser());
			$returnedWork->setSubmissionBox($this->getSubmissionBox());
			$returnedWork->setReturned(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
		}

		return $returnedWork;
	}

	/**
	 * Apply updates to the returned work record
	 *
	 * @return \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork
	 */
	private function processReturnedWork() {
		$returnedWork = $this->retrieveReturnedWorkRecord();

		/* Normalise - rubrics do it in a whole different way
		 */
		if ($this->getSubmissionBox()->isRubric()) {
			$normalisePayload = $this->getRubric();
		}
		else {
			$normalisePayload = $this->getMark();
		}

		/* Fire.
		 */
		$normalisedMarkArray = $this->getMarksheet()->normaliseMark($normalisePayload, $this->getSubmissionBox());

		/* Update the existing Returned Work record with new data
		 */
		$returnedWork->setMark($this->getMark());
		$returnedWork->setComment($this->getComment());
		$returnedWork->setMarker($_SESSION['user']->getId());
		$returnedWork->setNormalizedMark($normalisedMarkArray['norm_mark']);

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
	 * Set redirect
	 *
	 * @param string $redirect
	 */
	private function setRedirect($redirect) {
		$this->redirect = $redirect;
	}

	/**
	 * Get redirect
	 *
	 * @return string
	 */
	private function getRedirect() {
		return $this->redirect;
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
	 * @param null $returnedWorKMapper
	 */
	private function setReturnedWorKMapper($returnedWorKMapper) {
		$this->returnedWorKMapper = $returnedWorKMapper;
	}

	/**
	 * Get returned work mapper
	 *
	 * @return null
	 */
	private function getReturnedWorKMapper() {
		return $this->returnedWorKMapper;
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
}