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
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

class DAV_SubjectServer_SubjectList extends Sabre\DAV\SimpleCollection {

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * Is the subject list shown for the given path?
	 *
	 * The subject list will only be shown for the root path, usually.
	 *
	 * @param  string $path The path that is being displayed.
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
		$mapper = new SubmissionBoxMapper();
		$categories = array();
		foreach ($mapper->getSubmittable($this->getUser()) as $box) {
			/** @var Schoolbox\Assessment\Component\SubmissionBox $box */
			$category = $box->getCategory();
			if (array_key_exists($category->getId(), $categories)) {
				continue;
			}
			$categories[$category->getId()] = true;

			$codes = array();
			foreach ($category->getCodes() as $code) {
				/** @var Schoolbox\Resource\CategoryCode $code */
				$codes[] = $code->getCode();
			}
			$name = $category->getName();
			if (!empty($codes)) {
				$name .= ' ('.implode(', ', $codes).')';
			}
			$child = new DAV_SubjectServer_Subject($this->getUniqueName($this->sanitiseName($name)));
			$child
				->setCategory($category)
				->setUser($this->getUser())
				->loadChildren();
			$this->addChild($child);
		}
		return $this;
	}

	/**
	 * Strips error-causing characters from the name of the subject.
	 * Currently only strips out / characters as that's the only thing that
	 * directory names outright choke on.
	 *
	 * @param  string $name
	 * @return string
	 */
	protected function sanitiseName($name) {
		return strtr($name, array('/' => ''));
	}

	/**
	 * Attempts to resolve conflicts with subject names by appending a number to
	 * the name if it's already in use.
	 *
	 * @throws Sabre\DAV\Exception if this method couldn't create a unique name
	 *                             after 1000 attempts
	 * @param  string $name
	 * @return string
	 */
	protected function getUniqueName($name) {
		if ($this->childExists($name)) {
			for ($i = 1; $i <= 1000; $i++) {
				$new_name = $name.'-'.$i;
				if (!$this->childExists($new_name)) {
					return $new_name;
				}
			}
			//
			// Fell through all the way to here? That means we couldn't find a
			// unique name after way too many attempts.
			// Maybe you should have an exception instead.
			throw new Sabre\DAV\Exception('Could not assign a unique name to subject "'.$name.'"');
		}
		else {
			return $name;
		}
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
