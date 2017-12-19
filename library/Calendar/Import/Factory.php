<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar\Import;


class Factory {

	/**
	 * The person to whom these imported events are relevant
	 *
	 * @var int
	 */
	protected $user = null;

	/**
	 * The person who spawned the event
	 *
	 * @var int
	 */
	protected $author = null;

	/**
	 * A list of selected event_category relations
	 *
	 * @var array
	 */
	protected $categoryList = array();

	/**
	 * A list of selected event_group relations
	 *
	 * @var array
	 */
	protected $groupList = array();

	/**
	 * A list of selected event_campus relations
	 *
	 * @var array
	 */
	protected $campusList = array();

	/**
	 * Access level for each event imported
	 *
	 * @var int
	 */
	protected $access = 0;

	/**
	 * The source file from which to import
	 *
	 * @var null
	 */
	protected $file = null;

    /**
     * Source of import
     *
     * @var string
     */
    protected $source;

    /**
	 * Constructor
	 *
	 * @param $user
	 * @param $author
	 * @param $categoryList
	 * @param $groupList
	 * @param $campusList
	 * @param $access
	 * @param $file
	 */
	public function __construct($user, $author, $categoryList, $groupList, $campusList, $access, $file, $source = null) {
		$this->setUser($user);
		$this->setAuthor($author);
		$this->setCategoryList($categoryList);
		$this->setGroupList($groupList);
		$this->setCampusList($campusList);
		$this->setAccess($access);
		$this->setFile($file);
		$this->setSource($source);

	}

	/**
	 * Spawn strategy
	 *
	 * @return mixed
	 */
	public function build() {
		if (stristr(file_get_contents($this->getFile(), null, null, 0, 20), 'BEGIN:VCALENDAR')) {
			$classname = '\Calendar\Import\Vcal';
		}
		else {
			$classname = '\Calendar\Import\Csv';
		}

		return new $classname($this->getUser(), $this->getAuthor(), $this->getCategoryList(), $this->getGroupList(), $this->getCampusList(), $this->getAccess(), $this->getFile(), $this->getSource());
	}

	/* Getters/Setters
	 */

	/**
	 * Set author id
	 *
	 * @param null $author
	 */
	protected function setAuthor($author) {
		$this->author = $author;
	}

	/**
	 * Get author id
	 *
	 * @return null
	 */
	protected function getAuthor() {
		return $this->author;
	}

	/**
	 * Set campus list
	 *
	 * @param array $campusList
	 */
	protected function setCampusList($campusList) {
		$this->campusList = $campusList;
	}

	/**
	 * Get campus list
	 *
	 * @return array
	 */
	protected function getCampusList() {
		return $this->campusList;
	}

	/**
	 * Set file
	 *
	 * @param null $file
	 */
	protected function setFile($file) {
		$this->file = $file;
	}

	/**
	 * Get file
	 *
	 * @return null
	 */
	protected function getFile() {
		return $this->file;
	}

	/**
	 * Set group list
	 *
	 * @param array $groupList
	 */
	protected function setGroupList($groupList) {
		$this->groupList = $groupList;
	}

	/**
	 * Get group list
	 *
	 * @return array
	 */
	protected function getGroupList() {
		return $this->groupList;
	}

	/**
	 * Set user
	 *
	 * @param null $user
	 */
	protected function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return null
	 */
	protected function getUser() {
		return $this->user;
	}

	/**
	 * Set category list
	 *
	 * @param array $categoryList
	 */
	protected function setCategoryList($categoryList) {
		$this->categoryList = $categoryList;
	}

	/**
	 * Get category list
	 *
	 * @return array
	 */
	protected function getCategoryList() {
		return $this->categoryList;
	}

	/**
	 * Set access
	 *
	 * @param int $access
	 */
	protected function setAccess($access) {
		$this->access = $access;
	}

	/**
	 * Get access
	 *
	 * @return int
	 */
	protected function getAccess() {
		return $this->access;
	}

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }


    /**
     * @param mixed $source
     */
    public function setSource($source)
    {
        $this->source = $source;
    }
}