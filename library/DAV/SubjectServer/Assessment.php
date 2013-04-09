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

class DAV_SubjectServer_Assessment extends Sabre_DAV_SimpleCollection
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
	 * @todo implement once student submission mapper is merged.
	 *
	 * @param string          $name
	 * @param string|resource $data
	 *
	 * @return null ETags aren't relevant because the file isn't viewable after
	 *              being uploaded.
	 */
	public function createFile($name, $data = null) {
		parent::createDirectory($name, $data);
		return null;
	}
}
