<?php
/**
 * Eportfolio_Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Eportfolio_Authenticator {

	/**
	 * The currently logged in user, usually
	 *
	 * @var int
	 */
	private $currentUserId = -1;

	/**
	 * The owner id for the page being viewed
	 *
	 * @var int
	 */
	private $ownerId = -1;

	/**
	 * The eportfolio page currently being viewed
	 *
	 * @var int
	 */
	private $pageId = -1;

	/**
	 * An instance of user handler
	 *
	 * @var null
	 */
	private $userHandler = null;

	/**
	 * An instance of group handler
	 *
	 * @var null
	 */
	private $groupHandler = null;

	/**
	 * An instance of eportfolio handler
	 *
	 * @var null
	 */
	private $eportfolioHandler = null;

	/**
	 * These pages are always visible to everyone
	 *
	 * @var array
	 */
	private $publicPages = array('profile', 'showcase');

	/**
	 * Constructor
	 *
	 * @param $currentUserId
	 * @param $ownerId
	 * @param $pageId
	 */
	public function __construct($currentUserId, $ownerId, $pageId) {
		$this->setCurrentUserId($currentUserId);
		$this->setOwnerId($ownerId);
		$this->setPageId($pageId);

		$this->setUserHandler(new User());
		$this->setGroupHandler(new Group(HOMEPAGE_SERVICE));
		$this->setEportfolioHandler(new Eportfolio($this->getOwnerId(), $this->getPageId()));
	}

	/**
	 * Determine if a particular person owns a particular page
	 * This excludes the hardcoded public pages
	 *
	 * @return bool
	 */
	public function pageExists() {
		if (in_array($this->getPageId(), $this->getPublicPages()) || ($this->getPageId() == 'create')) {
			return true;
		}
		else {
			$pageMapper = new \Eportfolio\PageMapper();

			$exists = $pageMapper->findById($this->getPageId());

			if ($exists && ($exists->getDeletedAt() == null)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether the $userId can create a new page
	 */
	public function canCreate() {

		/* If $user can modify page, continue; otherwise die
		 * Superusers, staff and page owners can view contents.
		 * Selected parents, groups and individuals can view contents.
		 */
		$youAreAdmin = $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff();
		$youArePageOwner = ($this->getOwnerId() == $this->getCurrentUserId());

		if ($youAreAdmin || $youArePageOwner) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether the $userId can view $pageId
	 *
	 * @return bool
	 */
	public function canView() {

		/* If $user can modify page, continue; otherwise die
		 * Superusers, staff and page owners can view contents.
		 * Selected parents, groups and individuals can view contents.
		 */
		$youAreAdmin = $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff();
		$youArePageOwner = ($this->getEportfolioHandler()->getSettingsPageOwner() == $this->getCurrentUserId());

		/* The current user can either be in a group, be a parent, or be an individual (or all three)
		 * who has/have been given View access to a particular page.
		 */
		$youCanView = false;

		/* First check parental
		 */
		$parentSettingCollection = \Eportfolio\PermissionMapper::getInstance()->findParentSetting($this->getPageId());

		for($parentSettingCollection->rewind(); $parentSettingCollection->valid(); $parentSettingCollection->next()) {
			$current = $parentSettingCollection->current();

			if (($current['value'] == 1) && $this->getGroupHandler()->isParentOfChild($this->getEportfolioHandler()->getSettingsPageOwner())) {
				$youCanView = true;
			}
		}

		/* Now check groups
		 */
		$permittedGroups = array();

		$allGroups = $this->getGroupHandler()->involvedInCategoryList($this->getCurrentUserId());

		if (is_array($allGroups) && count($allGroups)) {
			foreach($allGroups as $currentGroup) {
				$permittedGroups[] = $currentGroup['id'];
			}

			$groupSettingCollection = \Eportfolio\PermissionMapper::getInstance()->findValues($this->getPageId(), 'group', $permittedGroups);

			if (count($groupSettingCollection)) {
				$youCanView = true;
			}
		}

		/* And now check individual permissions
		 */
		$permittedIndividuals = array($this->getCurrentUserId());

		$individualSettingCollection = \Eportfolio\PermissionMapper::getInstance()->findValues($this->getPageId(), 'student', $permittedIndividuals);

		if (count($individualSettingCollection)) {
			$youCanView = true;
		}

		/* These pages are always public
		 */
		if (in_array($this->getPageId(), $this->getPublicPages())) {
			$youCanView = true;
		}

		/* And now...the conclusion.
		 */
		if ($youAreAdmin || $youArePageOwner || $youCanView) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether the $userId can modify $pageId
	 *
	 * @return bool
	 */
	public function canModify() {

		/* If $user can modify page, continue; otherwise die
		 * Superusers, staff and page owners can view contents.
		 * Selected parents, groups and individuals can view contents.
		 */
		$youAreAdmin = $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff();
		$youArePageOwner = ($this->getEportfolioHandler()->getSettingsPageOwner() == $this->getCurrentUserId());
		$youArePublicPageOwner = (in_array($this->getPageId(), $this->getPublicPages()) && ($this->getOwnerId() == $this->getCurrentUserId()));

		/* And now...the conclusion.
		 */
		if ($youAreAdmin || $youArePageOwner || $youArePublicPageOwner) {
			return true;
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Set group handler
	 *
	 * @param null $groupHandler
	 */
	private function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return null
	 */
	private function getGroupHandler() {
		return $this->groupHandler;
	}

	/**
	 * Set page id
	 *
	 * @param int $pageId
	 */
	private function setPageId($pageId) {
		$this->pageId = $pageId;
	}

	/**
	 * Get page id
	 *
	 * @return int
	 */
	private function getPageId() {
		return $this->pageId;
	}

	/**
	 * Set user handler
	 *
	 * @param null $userHandler
	 */
	private function setUserHandler($userHandler) {
		$this->userHandler = $userHandler;
	}

	/**
	 * Get user handler
	 *
	 * @return null
	 */
	private function getUserHandler() {
		return $this->userHandler;
	}

	/**
	 * Set owner id
	 *
	 * @param int $ownerId
	 */
	private function setOwnerId($ownerId) {
		$this->ownerId = $ownerId;
	}

	/**
	 * Get owner id
	 *
	 * @return int
	 */
	private function getOwnerId() {
		return $this->ownerId;
	}

	/**
	 * Set eportfolio handler
	 *
	 * @param null $eportfolioHandler
	 */
	private function setEportfolioHandler($eportfolioHandler) {
		$this->eportfolioHandler = $eportfolioHandler;
	}

	/**
	 * Get eportfolio handler
	 *
	 * @return null
	 */
	private function getEportfolioHandler() {
		return $this->eportfolioHandler;
	}

	/**
	 * Set current user id
	 *
	 * @param int $currentUserId
	 */
	private function setCurrentUserId($currentUserId) {
		$this->currentUserId = $currentUserId;
	}

	/**
	 * Get current user id
	 *
	 * @return int
	 */
	private function getCurrentUserId() {
		return $this->currentUserId;
	}

	/**
	 * Set public pages
	 *
	 * @param array $publicPages
	 */
	private function setPublicPages($publicPages) {
		$this->publicPages = $publicPages;
	}

	/**
	 * Get public pages
	 *
	 * @return array
	 */
	private function getPublicPages() {
		return $this->publicPages;
	}
}