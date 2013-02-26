<?php
/**
 * A DAV collection representing the list of subjects that the current user has.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class DAV_SubjectServer_SubjectList extends Sabre_DAV_SimpleCollection {
	/**
	 * @var \Schoolbox\External\SubjectCollection
	 */
	protected $subjects;


	/**
	 * Retrieves the collection of subjects for the current user.
	 *
	 * @return array
	 */
	public function getChildren() {
		if (!empty($this->children)) {
			return $this->children;
		}
		foreach ($this->getSubjects() as $subject) {
			$this->children[] = new DAV_SubjectServer_Subject($subject);
		}
		return $this->children;
	}


	/**
	 * Retrieves the subject with this name for the given user.
	 *
	 * @param string $name
	 *
	 * @throws Sabre_DAV_Exception_NotFound if subject doesn't exist for user.
	 *
	 * @return DAV_SubjectServer_Subject
	 */
	public function getChild($name) {
		foreach ($this->getChildren() as $child) {
			if ($name === $child->getSubject()->getName()) {
				return $child;
			}
		}
		throw new Sabre_DAV_Exception_NotFound('The subject : ' . $name . ' could not be found');
	}


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
	 * Retrieves list of subjects, loading the list if required.
	 *
	 * @return \Schoolbox\External\SubjectCollection
	 */
	public function getSubjects() {
		if (!empty($this->subjects)) {
			return $this->subjects;
		}
		$mapper = new \Schoolbox\UserMapper();
		$this->subjects = $mapper->findWithSubjects($_SESSION['id'])->getSubjectCollection();
		return $this->subjects;
	}
}
