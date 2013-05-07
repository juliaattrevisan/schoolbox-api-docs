<?php
/**
 * A DAV collection representing a single assessment within a subject.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;

class DAV_SubjectServer_Assessment extends Sabre\DAV\SimpleCollection
{
	/**
	 * @var SubmissionBox
	 */
	protected $assessment;

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @param SubmissionBox $assessment
	 *
	 * @return $this
	 */
	public function setAssessment($assessment) {
		$this->assessment = $assessment;
		return $this;
	}

	/**
	 * @return SubmissionBox
	 */
	public function getAssessment() {
		return $this->assessment;
	}

	/**
	 * @param  User $user
	 *
	 * @return $this
	 */
	public function setUser($user) {
		$this->user = $user;
		return $this;
	}

	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Loads this student's submissions (and later on, returns).
	 *
	 * @return $this
	 */
	public function loadChildren() {
		$mapper = new SubmissionMapper();
		$files  = $mapper->findByColumnList(array(
			'owner'  => $this->getUser()->getId(),
			'box_id' => $this->getAssessment()->getId(),
		));
		foreach ($files as $file) {
			$file_location = $file->getFileLocation();
			if (file_exists($file_location)) {
				$child = new DAV_SubjectServer_Submission($file->getFilename(), file_get_contents($file_location));
				$child
					->setUser($this->getUser())
					->setSubmission($file)
				;
				$this->addChild($child);
			}
		}
		return $this;
	}

	/**
	 * Uploads a student submission to the assessment.
	 *
	 * @throws Sabre\DAV\Exception\Forbidden if the file cannot be uploaded
	 *
	 * @param string          $name
	 * @param string|resource $data
	 *
	 * @return null ETags aren't being returned for now.
	 */
	public function createFile($name, $data = null) {

		//
		// If a resource is supplied, its file could be deleted before it can
		// be copied to its destination.
		// Copy it to a temp file before this happens.
		$temp_file = tempnam(sys_get_temp_dir(), 'dav');
		if (!is_resource($data)) {
			file_put_contents($temp_file, $data);
		}
		else {
			$fp = fopen($temp_file, 'wb');
			stream_copy_to_stream($data, $fp, -1, 0);
			fclose($fp);
		}

		$late_timestamp = $this->getAssessment()->getDueDate()->getUnixTimestamp();
		$submit_date    = new DateTime('now');
		$is_late        = ($late_timestamp < $submit_date->getTimestamp());

		$submission = new Submission();
		$submission
			->setSubmissionBox($this->getAssessment())
			->setOwner($this->getUser())
			->setFilename($name)
			->setTempLocation($temp_file)
			->setSubmitted($submit_date)
			->setLate($is_late)
			->loadMimetypeFromFilename()
		;

		//
		// Reasons a student couldn't submit a file:
		// * They aren't in this subject
		// * The subject doesn't take submissions online
		// * The submission is late
		// * They've already submitted and it's not a multi-submission assess
		// All of these are covered in SubmissionMapper->checkUploadability
		$mapper = new SubmissionMapper();
		try {
			$mapper->checkUploadability($submission, array());
		}
		catch (Schoolbox\Resource\Exception $e) {
			throw new Sabre\DAV\Exception\Forbidden($e->getMessage(), $e->getCode(), $e);
		}
		$mapper->save($submission);
		return null;

//		$dav_file = new Sabre\DAV\SimpleFile(
//			$submission->getFilename(),
//			file_get_contents($submission->getFileLocation())
//		);
//		return $dav_file->getETag();
	}

	/**
	 * Gets the time which this assessment was uploaded.
	 *
	 * @return int
	 */
	public function getLastModified() {
		$submission = $this->getAssessment();
		return $submission->getOpenDate()->getUnixTimestamp();
	}
}
