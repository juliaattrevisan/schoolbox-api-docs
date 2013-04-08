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
	private $userId = -1;

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
	 * Constructor
	 *
	 * @param $userId
	 * @param $pageId
	 */
	public function __construct($userId, $pageId) {
		$this->setUserId($userId);
		$this->setPageId($pageId);

		$this->setUserHandler(new User());
		$this->setGroupHandler(new Group(HOMEPAGE_SERVICE));
		$this->setEportfolioHandler(new Eportfolio($this->getUserId(), $this->getPageId()));
	}

	/**
	 * Determine whether the $userId can view $pageId
	 *
	 * @return bool
	 */
	public function canView() {
		$owner = $this->getUserHandler()->getByID($this->getEportfolioHandler()->getSettingsPageOwner());

		/* If $user can modify page, continue; otherwise die
		 * Superusers, staff and page owners can view contents.
		 * Selected parents, groups and individuals can view contents.
		 */
		$youAreAdmin = $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff();
		$youArePageOwner = ($owner['uid'] == $this->getUserId());

		/* The current user can either be in a group, be a parent, or be an individual (or all three)
		 * who has/have been given View access to a particular page.
		 */
		$youCanView = false;

		/* First check parental
		 */
		$parentSettingCollection = Eportfolio_PermissionMapper::getInstance()->findParentSetting($this->getPageId());

		for($parentSettingCollection->rewind(); $parentSettingCollection->valid(); $parentSettingCollection->next()) {
			$current = $parentSettingCollection->current();

			if (($current['value'] == 1) && $this->getGroupHandler()->isParentOfChild($this->getEportfolioHandler()->getSettingsPageOwner())) {
				return true;
			}
		}

		/* Now check groups
		 */
		$permittedGroups = array();

		$allGroups = $this->getGroupHandler()->involvedInCategoryList($this->getUserId());

		if (is_array($allGroups) && count($allGroups)) {
			foreach($allGroups as $currentGroup) {
				$permittedGroups[] = $currentGroup['id'];
			}

			$groupSettingCollection = Eportfolio_PermissionMapper::getInstance()->findValues($this->getPageId(), 'group', $permittedGroups);

			print_r($groupSettingCollection);

		}

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
//		if ($userId can modify $pageId) {
//			return true;
//		}

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
	 * Set user id
	 *
	 * @param int $userId
	 */
	private function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * Get user id
	 *
	 * @return int
	 */
	private function getUserId() {
		return $this->userId;
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
}