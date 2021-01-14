<?php

/**
 * Eportfolio_Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Eportfolio\Permission;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Eportfolio_Authenticator
{

    /**
     * The currently logged in user, usually
     *
     * @var User
     */
    private User $currentUser;

    /**
     * The owner for the page being viewed
     *
     * @var User
     */
    private User $owner;

    /**
     * The eportfolio page currently being viewed
     *
     * @var int
     */
    private $pageId = -1;


    /**
     * These pages are always visible to everyone
     *
     * @var array
     */
    private $publicPages = ['profile', 'showcase'];

    /**
     * Feature flag to enable or disable ePortfolio globally
     */
    private bool $featureEnabled;


    /**
     * Constructor
     *
     * @param User $currentUser
     * @param int $ownerId
     * @param int $pageId
     */
    public function __construct(User $currentUser, int $ownerId, $pageId)
    {

        $this->owner = UserMapper::getInstance()->findById($ownerId);
        $this->currentUser = $currentUser;
        $this->featureEnabled = (bool)ConfigLoader::getInstance()->get('eportfolio_enabled');

        $this->pageId = $pageId;
    }

    /**
     * Determine if a particular person owns a particular page
     * This excludes the hardcoded public pages
     *
     * @return bool
     */
    public function pageExists(): bool
    {

        /* Check whether eportfolio is even enabled in the first place
         */
        if (!$this->featureEnabled || !$this->owner->getEportfolioEnable()) {
            return false;
        } elseif (in_array($this->getPageId(), $this->getPublicPages()) || ($this->getPageId() == 'create')) {
            return true;
        } else {
            $pageMapper = new \Eportfolio\PageMapper();

            try {
                // Check the page actually exists for the owner.
                $exists = $pageMapper->findOneByColumnList(
                    array(
                        'uid' => $this->owner->getId(),
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
        if ($this->currentUser->getRole()->isGuest()) {
            return false;
        }

        /* Check whether eportfolio is even enabled in the first place
         */
        if (!$this->featureEnabled || !$this->owner->getEportfolioEnable()) {
            return false;
        }

        /* If $user can modify page, continue; otherwise die
         * Superusers, staff and page owners can view contents.
         * Selected parents, groups and individuals can view contents.
         */
        $youAreAdmin = $this->currentUser->isSuperuser() || $this->currentUser->getRole()->isStaff();

        /* If a pageId was numeric, you are trying to access a particular page.
         *
         * Prevent url tampering by changing the user id to your own, while
         * retaining someone else's pageId as this allowed you to view other
         * people's pages as if they were your own.
         *
         * If it wasn't numeric, then you're viewing one of the hardcoded pages
         * which have no pageId.
         */

        $youArePageOwner = ($this->owner->getId() === $this->currentUser->getId());

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
        if ($this->currentUser->getRole()->isGuest()) {
            return false;
        }

        /* Check whether eportfolio is even enabled in the first place
         */
        if (!$this->featureEnabled || !$this->owner->getEportfolioEnable()) {
            return false;
        }

        /* If $user can modify page, continue; otherwise die
         * Superusers, staff and page owners can view contents.
         * Selected parents, groups and individuals can view contents.
         */
        $youAreAdmin = $this->currentUser->isSuperuser() || $this->currentUser->getRole()->isStaff();
        $youArePageOwner = ($this->owner->getId() === $this->currentUser->getId());

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
            if ($this->currentUser->getRole()->isParent()) {
                if ($this->currentUser->isParentOf($this->owner)) {
                    $youCanView = true;
                }
            } else {
                /* All students are allowed
                */
                $youCanView = true;
            }
        } else {
            /* User-created pages have their own unique permissions
             *
             * First check parental
             */
            $parentSettingCollection = \Eportfolio\PermissionMapper::getInstance()->findParentSetting(
                $this->getPageId()
            );

            foreach ($parentSettingCollection as $current) {
                /* @var $current \Eportfolio\Permission */
                if ($current->getValue() && $this->currentUser->isParentOf($this->owner)) {
                    $youCanView = true;
                }
            }

            /* Still can't view it?
             * Now check groups
             */
            if (!$youCanView) {
                $permittedGroups = [];

                $allGroups = FolderMapper::getInstance()->findForUser($this->currentUser);

                foreach ($allGroups as $currentGroup) {
                    $permittedGroups[] = $currentGroup->getId();
                }

                $groupSettingCollection = \Eportfolio\PermissionMapper::getInstance()->findValues(
                    $this->getPageId(),
                    Permission::TYPE_GROUP,
                    $permittedGroups
                );

                if (count($groupSettingCollection)) {
                    $youCanView = true;
                }
            }

            /* Still can't view it?
             * Now check individual permissions
             */
            if (!$youCanView) {
                $individualSettingCollection = \Eportfolio\PermissionMapper::getInstance()->findValues(
                    $this->getPageId(),
                    Permission::TYPE_STUDENT,
                    [$this->currentUser->getId()]
                );

                if (count($individualSettingCollection)) {
                    $youCanView = true;
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
        if ($this->currentUser->getRole()->isGuest()) {
            return false;
        }

        /* Check whether eportfolio is even enabled in the first place
         */
        if (!$this->featureEnabled || !$this->owner->getEportfolioEnable()) {
            return false;
        }

        /* If $user can modify page, continue; otherwise die
         * Superusers, staff and page owners can view contents.
         * Selected parents, groups and individuals can view contents.
         */
        $youAreAdmin = $this->currentUser->isSuperuser() || $this->currentUser->getRole()->isStaff();
        $youArePageOwner = ($this->owner->getId() === $this->currentUser->getId());

        $youArePublicPageOwner = (in_array($this->getPageId(), $this->getPublicPages()) &&
            ($this->owner->getId() === $this->currentUser->getId()));

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
     * Get page id
     *
     * @return int
     */
    private function getPageId()
    {
        return $this->pageId;
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
}
