<?php

use Schoolbox\User;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Calendar_Api_EventType_Abstract
 *
 * This is used to remember the calendar_connector id ($connectorId) because each event needs to know from whence it
 * came. This id is used to retrieve a particular event record from whatever source, to display event detail screen in
 * the Mobile version of the calendar (in Desktop, event details are rendered inline)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Calendar_Api_EventType_Abstract implements Calendar_Api_EventType_Interface
{
    /**
     * The calendar_connector.id from which this event was retrieved.
     * This is used to create a functioning event detail screen in Mobile.
     *
     * @var int
     */
    private $connectorId;

    /**
     * The user whose calendar we are viewing (not the current session user)
     *
     * @var Schoolbox\User
     */
    private $user;

    /**
     * The user who is viewing the current user (the current session)
     *
     * @var Schoolbox\User
     */
    private $viewer;

    public array $cacheUser = array();

    /**
     * @param int  $connectorId
     * @param User $user
     * @param User $viewer
     */
    public function __construct(int $connectorId, User $user, User $viewer)
    {
        $this->connectorId = $connectorId;
        $this->user        = $user;
        $this->viewer      = $viewer;
    }

    /* Getters/Setters
     */


    /**
     * Get calendar_connector id
     *
     * @return int
     */
    protected function getConnectorId(): int
    {
        return $this->connectorId;
    }


    /**
     * @return User
     */
    protected function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return User
     */
    protected function getViewer(): User
    {
        return $this->viewer;
    }

    /**
     * Helper method for loading this event type's calendar connector config.
     *
     * @return Calendar_Api_Config_Record|object
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    protected function loadConnectorConfiguration()
    {
        return Calendar_Api_Config_Mapper::getInstance()->findById(
            $this->connectorId
        );
    }

    /**
     * The new getPermission method, which uses `\Schoolbox\Permission\Access\Access::getFolderAccess`
     * under the hood.
     *
     * @param null|int  $folderID    The ID of the object to get permissions on.
     * @param int  $userID      The user to get permissions for.
     *                          If omitted, defaults to current user.
     * @param bool $cache       If false, ignore cache.
     * @return int
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function getPermission(?int $folderID, int $userID = 0, bool $cache = true): int
    {
        if ($userID == 0) {
            $sessionUser = $this->extractSessionUser();
            if ($sessionUser) {
                $userID = $sessionUser->getId();
                $this->cacheUser[$userID] = $sessionUser;
            } else {
                return UserAccess::ACCESS_DENY;
            }
        }

        // Cache users as well

        if (!isset($this->cacheUser[$userID]) || !$cache) {
            try {
                $this->cacheUser[$userID] = $user = \Schoolbox\UserMapper::getInstance()->findById($userID);
            } catch (\Schoolbox\Mapper\Exception $e) {
                return UserAccess::ACCESS_DENY;
            }
        } else {
            $user = $this->cacheUser[$userID];
        }

        return Access::getInstance()->getFolderAccess($user, (int)$folderID, $cache);
    }

    /**
     * Extract a user out of the session.
     *
     * @return User|null
     */
    private function extractSessionUser(): ?User
    {
        return isset($this->viewer) && $this->viewer instanceof User
            ? $this->viewer
            : null;
    }
}
