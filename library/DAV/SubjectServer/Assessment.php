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
	 * Currently, assessment items have no displayed children. Later, the
	 * assessment box could include the student's submissions.
	 *
	 * @return $this
	 */
	public function loadChildren() {
		return $this;
	}

	/**
	 * Uploads a student submission to the assessment.
	 *
	 * @param string          $name
	 * @param string|resource $data
	 *
	 * @return null ETags aren't relevant because the file isn't viewable after
	 *              being uploaded (for now at least)
	 */
	public function createFile($name, $data = null) {

		$temp_file = null;
		if (!is_resource($data)) {
			$temp_file = tempnam(sys_get_temp_dir(), 'dav');
			file_put_contents($temp_file, $data);
		}
		else {
			$metadata  = stream_get_meta_data($data);
			$temp_file = $metadata['uri'];
		}

		$late_timestamp = $this->getAssessment()->getDueDate()->getUnixTimestamp();
		$submit_date    = new DateTime('now');
		$is_late        = ($late_timestamp < $submit_date->getTimestamp());

		$mapper     = new SubmissionMapper();
		$submission = new Submission();
		$submission
			->setSubmissionBox($this->getAssessment())
			->setOwner($this->getUser())
			->setFilename($name)
			->setTempLocation($temp_file)
			->setSubmitted(new Alaress_Date_Format($submit_date))
			->setLate($is_late)
		;

		$mapper->save($submission);
		return null;
	}
}
