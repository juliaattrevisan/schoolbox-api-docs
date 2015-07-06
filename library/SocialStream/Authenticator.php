<?php
/**
 * SocialStream_Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;

use Component_Instance;

/**
 * Class Authenticator
 * @package SocialStream
 */
class Authenticator
{

    /**
     * A SocialStream_Post instance
     *
     * @var null
     */
    private $newPost = null;

    /**
     * A component instance
     *
     * @var Component_Instance
     */
    private $instance = null;

    /**
     * A user instance
     *
     * @var \Schoolbox\User
     */
    private $currentUser = null;

    /**
     * Default permission level
     *
     * @var int
     */
    private $permission = -1;

    /**
     * Constructor
     *
     * @param \SocialStream_Post_Abstract $newPost
     * @param Component_Instance $instance
     * @param \Schoolbox\User $currentUser
     */
    public function __construct(\SocialStream_Post_Abstract $newPost, Component_Instance $instance, \Schoolbox\User $currentUser)
    {
        $this->setNewPost($newPost);
        $this->setInstance($instance);
        $this->setCurrentUser($currentUser);
        $this->setPermission($this->determinePermission());
    }

    /**
     * Determine permission level for the current homepage
     *
     * @return bool|int
     */
    private function determinePermission()
    {
        $userId = $this->getCurrentUser()->getId();
        if ($this->getCurrentUser()->isSuperuser()) {
            $permission = FULL_ACCESS;
        } elseif (!$userId) {
            // Don't fall back to getPermission() call with session user,
            // instead deny access if there's no actual user
            $permission = DENY_ACCESS;
        } else {
            $groupHandler = new \Group();
            //If we are dealing with a post, check its permission, else check permission of component for rendering purposes
            $homepageID = $this->getNewPost()->getHomepageId() ? $this->getNewPost()->getHomepageId() : $this->getInstance()->getIdentifier();
            $permission = $groupHandler->getPermission($homepageID, $userId);
        }

        return $permission;
    }

    /**
     * Find a particular config value
     *
     * @param $key
     * @return mixed
     */
    private function findConfigValue($key)
    {
        foreach ($this->getInstance()->getConfig() as $currentConfig) {
            /** @var \Component_ConfigDefinition $currentConfig */
            if ($currentConfig->getKey() == $key) {
                $value = $currentConfig->getValue();
                if (!isset($value)) {
                    $value = $currentConfig->getDefaultValue();
                }
                return $value;
            }
        }
    }

    /**
     * Determine whether $currentUser can softdelete $newPost and its threads
     *
     * @return bool
     */
    public function canDelete()
    {
        if ($this->getPermission() >= WRITE_ACCESS) {
            return true;
        }

        /* Post/Comment/Reply owner can softdelete a post's children regardless
         * of their children's owner_ids
         */
        if ($this->getCurrentUser()->getId() == $this->getNewPost()->getOwnerId()->getId()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether $currentUser can collaborate on $instance
     *
     * @return bool
     */
    public function canCollaborate()
    {
        if ($this->getPermission() >= WRITE_ACCESS) {
            return true;
        }

        if ($this->findConfigValue('collaborate')) {
            if ($this->getPermission() >= READ_ACCESS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether $currentUser can comment on $instance
     * This is used when processing a form submission
     *
     * @return bool
     */
    public function canAddComment()
    {
        if ($this->getPermission() >= WRITE_ACCESS) {
            return true;
        }

        if ($this->findConfigValue('commentsAllowed')) {
            if ($this->getPermission() >= READ_ACCESS) {
                if ($this->getNewPost()->getRootId() > 0) {
                    if ($this->getNewPost()->getParentId() > 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Determine whether $currentUser can comment on $instance
     * This is used when determining if the comment form is available
     *
     * @return bool
     */
    public function canComment()
    {
        if ($this->getPermission() >= WRITE_ACCESS) {
            return true;
        }

        if ($this->findConfigValue('commentsAllowed')) {
            if ($this->getPermission() >= READ_ACCESS) {
                return true;
            }
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set current user
     *
     * @param \Schoolbox\User $currentUser
     */
    private function setCurrentUser($currentUser)
    {
        $this->currentUser = $currentUser;
    }

    /**
     * Get current user
     *
     * @return \Schoolbox\User
     */
    private function getCurrentUser()
    {
        return $this->currentUser;
    }

    /**
     * Set instance
     *
     * @param Component_Instance $instance
     */
    private function setInstance($instance)
    {
        $this->instance = $instance;
    }

    /**
     * Get instance
     *
     * @return Component_Instance
     */
    private function getInstance()
    {
        return $this->instance;
    }

    /**
     * Set permission
     *
     * @param int $permission
     */
    private function setPermission($permission)
    {
        $this->permission = $permission;
    }

    /**
     * Get permission
     *
     * @return int
     */
    private function getPermission()
    {
        return $this->permission;
    }

    /**
     * Set new post
     *
     * Public for setting values from within a loop
     *
     * @param null $newPost
     */
    public function setNewPost($newPost)
    {
        $this->newPost = $newPost;
    }

    /**
     * Get new post
     *
     * @return null
     */
    private function getNewPost()
    {
        return $this->newPost;
    }
}
