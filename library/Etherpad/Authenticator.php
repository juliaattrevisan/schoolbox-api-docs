<?php

/**
 * Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Etherpad;

use Schoolbox\Permission\Access\UserAccess;

class Authenticator
{

    /**
     * An instance of \Group
     *
     * @var \Group
     */
    private $groupHandler;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setGroupHandler(new \Group());
    }

    /**
     * Determine if you can create a pad for $homepageId
     *
     * @param int $homepageId
     * @return bool
     */
    public function canCreate($homepageId)
    {
        if ($this->getGroupHandler()->getPermission($homepageId) > UserAccess::ACCESS_READ) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a user is part of an authoring group
     *
     * @param int $homepageId
     * @return bool
     */
    public function isParticipator($homepageId)
    {
        if ($this->getGroupHandler()->getPermission($homepageId) >= UserAccess::ACCESS_READ) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set group handler
     *
     * @param \Group $groupHandler
     */
    public function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group handler
     *
     * @return \Group
     */
    public function getGroupHandler()
    {
        return $this->groupHandler;
    }
}
