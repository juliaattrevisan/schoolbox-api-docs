<?php
/**
 * A DAV collection representing a single submission within an assessment.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;

class DAV_SubjectServer_Submission extends Sabre\DAV\SimpleFile
{

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var Submission
	 */
	protected $submission;

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
	 * @param Submission $submission
	 *
	 * @return $this
	 */
	public function setSubmission($submission) {
		$this->submission = $submission;
		return $this;
	}

	/**
	 * @return Submission
	 */
	public function getSubmission() {
		return $this->submission;
	}

	/**
	 * Gets the time which this file was uploaded.
	 *
	 * @return int
	 */
	public function getLastModified() {
		$submission = $this->getSubmission();
		if ($submission instanceof Submission) {
			$date = $submission->getSubmitted();
			if ($date instanceof Alaress_Date_Format) {
				return $date->getUnixTimestamp();
			}
		}
		return time();
	}

	/**
	 * Updates a file that has been created already.
	 *
	 * This is required for some WebDAV clients -
	 *
	 * @param resource $data
	 *
	 * @return null
	 */
	public function put($data) {
		//
		// Load this file's contents.
		$temp_file = tempnam(sys_get_temp_dir(), 'dav');
		$fp = fopen($temp_file, 'wb');
		stream_copy_to_stream($data, $fp, -1, 0);
		fclose($fp);

		//
		// And put it in the submission object.
		$mapper = new SubmissionMapper();
		$mapper->save($this->getSubmission()->setTempLocation($temp_file));
		return null;
	}
}
