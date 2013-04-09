<?php
/**
 * A DAV collection representing the list of subjects that the current user has.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\External\SubjectMapper;

class DAV_SubjectServer_SubjectList extends Sabre_DAV_SimpleCollection {

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * Is the subject list shown for the given path?
	 *
	 * The subject list will only be shown for the root path, usually.
	 *
	 * @param $path The path that is being displayed.
	 *
	 * @return bool
	 */
	public function isSubjectListShown($path) {
		return !empty($_SESSION['subjects']) && $this->isThisDirectChildOfPath($path);
	}

	/**
	 * Is this subject list a direct descendant of the given path?
	 *
	 * @param $path
	 *
	 * @return bool
	 */
	protected function isThisDirectChildOfPath($path) {
		$this_path_parts = $this->getPathParts($this->name);
		$path_parts      = $this->getPathParts($path);
		array_pop($this_path_parts);
		return $this_path_parts === $path_parts;
}

	/**
	 * Gets the parts of the given path, used by isThisDirectChildOfPath() to
	 * determine whether this item is in another path.
	 *
	 * @param $path
	 *
	 * @return array
	 */
	protected function getPathParts($path) {
		return preg_split('/[\\\\\/]+/', $path, -1, PREG_SPLIT_NO_EMPTY);
	}

	/**
	 * Loads list of child nodes from the given user's subjects.
	 *
	 * @return $this
	 */
	public function loadChildren() {
		$subject_mapper = new SubjectMapper();
		foreach ($subject_mapper->findAllForStudent($this->getUser()) as $subject) {
			$child = new DAV_SubjectServer_Subject(sprintf('%s (%s)', $subject->getName(), $subject->getCode()));
			$child
				->setSubject($subject)
				->setUser($this->getUser())
				->loadChildren();
			$this->addChild($child);
		}
		return $this;
	}

	/**
	 * @param  \Schoolbox\User $user
	 *
	 * @return $this
	 */
	public function setUser($user) {
		$this->user = $user;
		return $this;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getUser() {
		return $this->user;
	}
}
