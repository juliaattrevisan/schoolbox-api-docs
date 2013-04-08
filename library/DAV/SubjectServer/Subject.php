<?php
/**
 * A DAV collection representing a single subject that a user has.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBoxMapper;

class DAV_SubjectServer_Subject extends Sabre_DAV_SimpleCollection
{
	/**
	 * @var \Schoolbox\External\Subject
	 */
	protected $subject;

	/**
	 * @param \Schoolbox\External\Subject $subject
	 *
	 * @return $this
	 */
	public function setSubject($subject) {
		$this->subject = $subject;
		return $this;
	}

	/**
	 * @return \Schoolbox\External\Subject
	 */
	public function getSubject() {
		return $this->subject;
	}

	/**
	 * Loads children from the subject set in $this->setSubject().
	 *
	 * @return $this
	 */
	public function loadChildrenFromSubject() {
		$mapper = new SubmissionBoxMapper();
		foreach ($mapper->getSubmittable($this->subject->getId()) as $assessment) {
			$name  = $assessment->getName();
			$child = new DAV_SubjectServer_Assessment($name);
			$child->setAssessment($assessment);
			$this->addChild($child);
		}
		return $this;
	}
}
