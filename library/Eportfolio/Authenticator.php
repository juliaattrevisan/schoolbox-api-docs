<?php

/**
 * Eportfolio_Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Eportfolio\Permission;
use Eportfolio\PermissionMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;

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
     */
    private ?int $pageId;

    /**
     * Feature flag to enable or disable ePortfolio globally
     */
    private bool $featureEnabled;


    /**
     * Constructor
     *
     * @param User     $currentUser
     * @param User     $owner
     * @param int|null $pageId
     */
    public function __construct(User $currentUser, User $owner, ?int $pageId = null)
    {
        $this->owner          = $owner;
        $this->currentUser    = $currentUser;
        $this->featureEnabled = (bool)ConfigLoader::getInstance()->get('eportfolio_enabled');

        $this->pageId = $pageId;
    }

    /**
     * Determine whether the $userId can create a new page
     *
     * @return bool
     */
    public function canCreate(): bool
    {
        if (!$this->isEportfolioEnabled()) {
            return false;
        }

        return $this->isOwnerOrAdmin();
    }

    /**
     * Determine whether the $userId can view $pageId
     *
     * @return bool
     */
    public function canView(): bool
    {
        if (!$this->isEportfolioEnabled()) {
            return false;
        }

        if ($this->isOwnerOrAdmin()) {
            return true;
        }

        /* The current user can either be in a group, be a parent, or be an individual (or all three)
         * who has/have been given View access to a particular page.
         */
        $youCanView = false;

        /* These pages are hardcoded and have special conditions
         */
        if (null === $this->pageId) {
            /* Parents of the page owner are allowed
             */
            if ($this->currentUser->getRole()->isParent()) {
                if ($this->currentUser->isParentOf($this->owner)) {
                    $youCanView = true;
                }
            } elseif ($this->currentUser->getRole()->isStudent()) {
                /* All students are allowed
                */
                $youCanView = true;
            }
        } else {
            /* User-created pages have their own unique permissions
             *
             * First check parental
             */
            $parentSettingCollection = PermissionMapper::getInstance()->findParentSetting(
                $this->pageId
            );

            foreach ($parentSettingCollection as $current) {
                /* @var $current Eportfolio\Permission */
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

                $groupSettingCollection = PermissionMapper::getInstance()->findValues(
                    $this->pageId,
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
                $individualSettingCollection = PermissionMapper::getInstance()->findValues(
                    $this->pageId,
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
    public function canModify(): bool
    {
        if (!$this->isEportfolioEnabled()) {
            return false;
        }

        if ($this->isOwnerOrAdmin()) {
            return true;
        }

        // check whether the current user is public page owner
        return $this->owner->getId() === $this->currentUser->getId();
    }

    /**
     * Check whether eportfolio is enabled
     * @return bool
     */
    private function isEportfolioEnabled(): bool
    {
        /*
         * Guest is not allowed
         * Eportfolio module is enabled
         * Eportfolio is enabled for this user
         */
        return !$this->currentUser->getRole()->isGuest()
            && $this->featureEnabled
            && $this->owner->getEportfolioEnable();
    }

    /**
     * Check whether the current user has enough permission or the current user is the owner
     * @return bool
     */
    private function isOwnerOrAdmin(): bool
    {
        /* If $user can modify page, continue; otherwise die
         * Superusers, staff and page owners can view contents.
         * Selected parents, groups and individuals can view contents.
         */
        $youAreAdmin = $this->currentUser->isSuperuser()
            || ($this->currentUser->getRole()->isStaff() && $this->currentUser->getRole()->getAccessEportfolio());

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

        return $youAreAdmin || $youArePageOwner;
    }
}
