<?php
/**
 * A DAV collection representing a single assessment within a subject.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class DAV_SubjectServer_Assessment extends Sabre_DAV_SimpleCollection
{
	/**
	 * @var \Schoolbox\Assessment\Component\SubmissionBox
	 */
	protected $assessment;

	/**
	 * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
	 *
	 * @return $this
	 */
	public function setAssessment($assessment) {
		$this->assessment = $assessment;
		return $this;
	}

	/**
	 * @return \Schoolbox\Assessment\Component\SubmissionBox
	 */
	public function getAssessment() {
		return $this->assessment;
	}
}
