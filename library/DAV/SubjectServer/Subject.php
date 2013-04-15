<?php
/**
 * A DAV collection representing a single subject that a user has.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\Resource\Category;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

class DAV_SubjectServer_Subject extends Sabre\DAV\SimpleCollection
{
	/**
	 * @var Category
	 */
	protected $category;

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @param Category $category
	 *
	 * @return $this
	 */
	public function setCategory($category) {
		$this->category = $category;
		return $this;
	}

	/**
	 * @return Category
	 */
	public function getCategory() {
		return $this->category;
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
		foreach ($mapper->getSubmittable($this->getUser(), $this->getCategory()) as $assessment) {
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
