<?php

/**
 * Eportfolio_Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Authenticator
{

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
     * A user record from handler
     *
     * @var array
     */
    private $owner = array();

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
    public function __construct($currentUserId, $ownerId, $pageId)
    {
        $this->setCurrentUserId($currentUserId);
        $this->setOwnerId($ownerId);
        $this->setPageId($pageId);

        $this->setUserHandler(new User());
        $this->setGroupHandler(new Group(HOMEPAGE_SERVICE));
        try {
            $this->setEportfolioHandler(new Eportfolio($this->getOwnerId(), $this->getPageId()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->setEportfolioHandler(new Eportfolio($this->getOwnerId(), null));
        }

        /* The ePortfolio owner
         */
        $this->setOwner($this->getUserHandler()->getById($this->getOwnerId()));
    }

    /**
     * Determine whether the owner's eportfolio is actually enabled
     *
     * @param $user
     * @return bool
     */
    private function isEportfolioEnabled($user)
    {
        if (is_array($user) && ($user['eportfolio_enabled'] == 1)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a particular person owns a particular page
     * This excludes the hardcoded public pages
     *
     * @return bool
     */
    public function pageExists()
    {

        /* Check whether eportfolio is even enabled in the first place
         */
        if (!$this->isEportfolioEnabled($this->getOwner())) {
            return false;
        } elseif (in_array($this->getPageId(), $this->getPublicPages()) || ($this->getPageId() == 'create')) {
            return true;
        } else {
            $pageMapper = new \Eportfolio\PageMapper();

            try {
                // Check the page actually exists for the owner.
                $exists = $pageMapper->findOneByColumnList(
                    array(
                        'uid' => $this->getOwner(),
                        'page_id' => $this->getPageId(),
                    )
                );
                if ($exists && !$exists->isDeleted()) {
                    return true;
                }
            } catch (Schoolbox\Mapper\Exception $e) {
            }
        }

        return false;
    }

    /**
     * Determine whether the $userId can create a new page
     */
    public function canCreate()
    {

        /* Hostile welcome for guests
         */
        if ($this->getGroupHandler()->isGuest()) {
            return false;
        }

        /* Check whether eportfolio is even enabled in the first place
         */
        if (!$this->isEportfolioEnabled($this->getOwner())) {
            return false;
        }

        /* If $user can modify page, continue; otherwise die
         * Superusers, staff and page owners can view contents.
         * Selected parents, groups and individuals can view contents.
         */
        $youAreAdmin = $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff();

        /* If a pageId was numeric, you are trying to access a particular page.
         *
         * Prevent url tampering by changing the user id to your own, while
         * retaining someone else's pageId as this allowed you to view other
         * people's pages as if they were your own.
         *
         * If it wasn't numeric, then you're viewing one of the hardcoded pages
         * which have no pageId.
         */
        if (is_numeric($this->getPageId())) {
            $youArePageOwner = ($this->getEportfolioHandler()->getSettingsPageOwner() == $this->getCurrentUserId());
        } else {
            $youArePageOwner = ($this->getOwnerId() == $this->getCurrentUserId());
        }

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
    public function canView()
    {

        /* Hostile welcome for guests
         */
        if ($this->getGroupHandler()->isGuest()) {
            return false;
        }

        /* Check whether eportfolio is even enabled in the first place
         */
        if (!$this->isEportfolioEnabled($this->getOwner())) {
            return false;
        }

        /* If $user can modify page, continue; otherwise die
         * Superusers, staff and page owners can view contents.
         * Selected parents, groups and individuals can view contents.
         */
        $youAreAdmin = $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff();
        $youArePageOwner = ($this->getEportfolioHandler()->getSettingsPageOwner() == $this->getCurrentUserId());

        /* Skip all that individual permissions stuff if possible
         */
        if ($youAreAdmin || $youArePageOwner) {
            return true;
        }

        /* The current user can either be in a group, be a parent, or be an individual (or all three)
         * who has/have been given View access to a particular page.
         */
        $youCanView = false;

        /* These pages are hardcoded and have special conditions
         */
        if (in_array($this->getPageId(), $this->getPublicPages())) {

            /* Parents of the page owner are allowed
             */
            if ($this->getGroupHandler()->isParent()) {
                if ($this->getGroupHandler()->isParentOfChild($this->getOwnerId())) {
                    $youCanView = true;
                }
            } /* All students are allowed
			 */
            else {
                $youCanView = true;
            }
        } /* User-created pages have their own unique permissions
		 */
        else {

            /* Prevent your id allowing you to view other people's pages by checking whether
             * the url's user id is the same as the page owner's. This prevents you substituting
             * your id into the url to view someone else's page
             */
            if ($this->getOwnerId() == $this->getEportfolioHandler()->getSettingsPageOwner()) {

                /* First check parental
                 */
                $parentSettingCollection = \Eportfolio\PermissionMapper::getInstance()->findParentSetting(
                    $this->getPageId()
                );

                foreach ($parentSettingCollection as $current) {
                    /* @var $current \Eportfolio\Permission */
                    if (($current->getValue()) && $this->getGroupHandler()->isParentOfChild(
                            $this->getEportfolioHandler()->getSettingsPageOwner()
                        )
                    ) {
                        $youCanView = true;
                    }
                }

                /* Still can't view it?
                 * Now check groups
                 */
                if (!$youCanView) {
                    $permittedGroups = array();

                    $allGroups = $this->getGroupHandler()->involvedInCategoryList($this->getCurrentUserId());

                    if (is_array($allGroups) && count($allGroups)) {
                        foreach ($allGroups as $currentGroup) {
                            $permittedGroups[] = $currentGroup['id'];
                        }

                        $groupSettingCollection = \Eportfolio\PermissionMapper::getInstance()->findValues(
                            $this->getPageId(),
                            'group',
                            $permittedGroups
                        );

                        if (count($groupSettingCollection)) {
                            $youCanView = true;
                        }
                    }
                }

                /* Still can't view it?
                 * Now check individual permissions
                 */
                if (!$youCanView) {
                    $permittedIndividuals = array($this->getCurrentUserId());

                    $individualSettingCollection = \Eportfolio\PermissionMapper::getInstance()->findValues(
                        $this->getPageId(),
                        'student',
                        $permittedIndividuals
                    );

                    if (count($individualSettingCollection)) {
                        $youCanView = true;
                    }
                }
            }
        }

        /* And now...the conclusion.
         */
        if ($youCanView) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the $userId can modify $pageId
     *
     * @return bool
     */
    public function canModify()
    {

        /* Hostile welcome for guests
         */
        if ($this->getGroupHandler()->isGuest()) {
            return false;
        }

        /* Check whether eportfolio is even enabled in the first place
         */
        if (!$this->isEportfolioEnabled($this->getOwner())) {
            return false;
        }

        /* If $user can modify page, continue; otherwise die
         * Superusers, staff and page owners can view contents.
         * Selected parents, groups and individuals can view contents.
         */
        $youAreAdmin = $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff();
        $youArePageOwner = ($this->getEportfolioHandler()->getSettingsPageOwner() == $this->getCurrentUserId());
        $youArePublicPageOwner = (in_array($this->getPageId(), $this->getPublicPages()) && ($this->getOwnerId(
                ) == $this->getCurrentUserId()));

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
     * @param Group $groupHandler
     */
    private function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group handler
     *
     * @return Group
     */
    private function getGroupHandler()
    {
        return $this->groupHandler;
    }

    /**
     * Set page id
     *
     * @param int $pageId
     */
    private function setPageId($pageId)
    {
        $this->pageId = $pageId;
    }

    /**
     * Get page id
     *
     * @return int
     */
    private function getPageId()
    {
        return $this->pageId;
    }

    /**
     * Set user handler
     *
     * @param null $userHandler
     */
    private function setUserHandler($userHandler)
    {
        $this->userHandler = $userHandler;
    }

    /**
     * Get user handler
     *
     * @return null
     */
    private function getUserHandler()
    {
        return $this->userHandler;
    }

    /**
     * Set owner id
     *
     * @param int $ownerId
     */
    private function setOwnerId($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    /**
     * Get owner id
     *
     * @return int
     */
    private function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Set eportfolio handler
     *
     * @param Eportfolio $eportfolioHandler
     */
    private function setEportfolioHandler($eportfolioHandler)
    {
        $this->eportfolioHandler = $eportfolioHandler;
    }

    /**
     * Get eportfolio handler
     *
     * @return Eportfolio
     */
    private function getEportfolioHandler()
    {
        return $this->eportfolioHandler;
    }

    /**
     * Set current user id
     *
     * @param int $currentUserId
     */
    private function setCurrentUserId($currentUserId)
    {
        $this->currentUserId = $currentUserId;
    }

    /**
     * Get current user id
     *
     * @return int
     */
    private function getCurrentUserId()
    {
        return $this->currentUserId;
    }

    /**
     * Set public pages
     *
     * @param array $publicPages
     */
    private function setPublicPages($publicPages)
    {
        $this->publicPages = $publicPages;
    }

    /**
     * Get public pages
     *
     * @return array
     */
    private function getPublicPages()
    {
        return $this->publicPages;
    }

    /**
     * Set owner
     *
     * @param array $owner
     */
    private function setOwner($owner)
    {
        $this->owner = $owner;
    }

    /**
     * Get owner
     *
     * @return array
     */
    private function getOwner()
    {
        return $this->owner;
    }
}