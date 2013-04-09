<?php
/**
 * A DAV collection representing a single subject that a user has.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\External\Subject;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

class DAV_SubjectServer_Subject extends Sabre_DAV_SimpleCollection
{
	/**
	 * @var Subject
	 */
	protected $subject;

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @param Subject $subject
	 *
	 * @return $this
	 */
	public function setSubject($subject) {
		$this->subject = $subject;
		return $this;
	}

	/**
	 * @return Subject
	 */
	public function getSubject() {
		return $this->subject;
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
	 * Loads children from the subject and user set in $this->setSubject().
	 *
	 * @return $this
	 */
	public function loadChildren() {
		$mapper = new SubmissionBoxMapper();
		foreach ($mapper->getSubmittable($this->subject->getId()) as $assessment) {
			$child = new DAV_SubjectServer_Assessment($assessment->getName());
			$child
				->setAssessment($assessment)
				->setUser($this->getUser())
				->loadChildren();
			$this->addChild($child);
		}
		return $this;
	}
}
