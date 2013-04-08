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
	 * Retrieves the collection of assessments for this subject.
	 *
	 * @return array
	 */
	public function getChildren() {
		if (!empty($this->children)) {
			return $this->children;
		}
		foreach ($this->getAssessments() as $assessment) {
			$name  = $assessment->getName();
			$child = new DAV_SubjectServer_Assessment($name);
			$child->setAssessment($assessment);
			$this->children[$name] = $child;
		}
		return $this->children;
	}


	/**
	 * @param \Schoolbox\External\Subject $subject
	 */
	public function setSubject($subject) {
		$this->subject = $subject;
	}


	/**
	 * @return \Schoolbox\External\Subject
	 */
	public function getSubject() {
		return $this->subject;
	}


	/**
	 * Retrieves list of assessments for this subject.
	 *
	 * @return Collection
	 */
	public function getAssessments() {
		$mapper = new SubmissionBoxMapper();
		return $mapper->getSubmittable($this->subject->getId());
	}
}
