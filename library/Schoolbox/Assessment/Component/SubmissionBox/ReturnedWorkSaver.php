<?php
/**
 * ReturnedWorkSaver
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;


class ReturnedWorkSaver {

	/**
	 * An instance of ReturnedWork
	 *
	 * @var null
	 */
	private $returnedWork = null;

	/**
	 * @param array $return The returned work to save.
	 * @param array $rubric The rubric data to save, if there is one.
	 * @param array $file   The file to add, if there is one.
	 *
	 * @return bool
	 */
	public function saveReturn($return, $rubric = null, $file = null) {
		$return_mapper   = ReturnedWorkMapper::getInstance();
		$returned_work   = new ReturnedWork();
		$box_id          = $return['box_id'];
		$student_id      = $return['owner'];
		$update_existing = false;
		try {
			/** @var ReturnedWork $returned_work */
			$returned_work = $return_mapper->findOneByColumnList(
				array('owner' => $student_id, 'box_id' => $box_id,),
				array('returned' => 'desc')
			);

			// Is this outside of the update window? if so, create a new one.
			$return_time = $returned_work->getReturned();
			$window_ago  = new \Alaress_Date_Format(time() - update_window, 'U');
			if ($return_time->getUnixTimestamp() < $window_ago->getUnixTimestamp()) {
				$returned_work = new ReturnedWork();
			}
			else {
				$update_existing = true;
			}
		}
		catch (\Schoolbox\Mapper\Exception $e) {}
		if (!$update_existing) {
			$returned_work
				->setOwner(\UserMapper::getInstance()->findById($student_id))
				->setSubmissionBox(\Schoolbox\Assessment\Component\SubmissionBoxMapper::getInstance()->findById($box_id))
				->setReturned(new \Alaress_Date_Format(new \DateTime('now')))
			;
		}
		$returned_work
			->setComment($return['comment'])
			->setMark($return['mark'])
			->setMarker($_SESSION['user'])
			->setNormalizedMark($return['norm_mark'])
		;
		// Is there a file? If so, upload it.
		if (!empty($file)) {
			$returned_work
				->setFilename($file['name'])
				->setTempLocation($file['tmp_name'])
				->loadFilesizeFromTempLocation();
			;
		}
		$return_mapper->save($returned_work);

		// Rubrics have no mappers yet, so handle them the old-fashioned way.
		$return_id = $returned_work->getId();
		if (!empty($return_id) && $rubric) {
			$rubric_handler = new \DueWork_Rubric_Handler();
			$stamp          = null;
			if ($update_existing) {
				$stamp = $rubric_handler->getLatestMarkTimestamp($student_id, $box_id);
			}
			$rubric_handler->saveMarks($rubric, $student_id, $box_id, $stamp);
		}

		/* Remember the returnedWork that was just created/updated
		 */
		$this->setReturnedWork($returned_work);

		return !empty($return_id);
	}

	/* Getters/Setters
	 */

	/**
	 * Set returned work
	 *
	 * @param null $returnedWork
	 */
	private function setReturnedWork($returnedWork) {
		$this->returnedWork = $returnedWork;
	}

	/**
	 * Get returned work
	 *
	 * @return null
	 */
	public function getReturnedWork() {
		return $this->returnedWork;
	}
}