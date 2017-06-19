<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;

use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as Folder;

class Access
{

    /**
     * Permission/access level constants
     */

    const VISIBILITY_EVERYONE = 0;                 // Accessible by everyone
    const VISIBILITY_STAFF = 1;                    // Accessible by staff only
    const VISIBILITY_STAFF_PARENT = 2;             // Accessible by staff & parents only

    /**
     * Cache folder access calls
     *
     * Indexed by user.id, then folder.id
     *
     * @var int[][]
     */
    protected $cachedFolderAccess = [];

    /**
     * Stores the singleton instance
     *
     * @var array[AccessMapper]
     */
    static protected $instance;

    /**
     * Retrieves the singleton instance of this class.
     *
     * @return static
     */
    public static function getInstance()
    {
        $class_name = get_called_class();

        if (!isset(self::$instance[$class_name])) {
            self::$instance[$class_name] = new static();
        }
        return self::$instance[$class_name];
    }


    /**
     * Is the specified user an instructor for the specified folder ?
     *
     * An instructor is any user with WRITE+ permission to a folder
     *
     * @param User   $user
     * @param Folder $folder
     * @return bool
     */
    public function isInstructor(User $user, Folder $folder)
    {
        return $this->getFolderAccess($user, $folder->getId()) >= UserAccess::ACCESS_WRITE;
    }

    /**
     * Is the specified user a learner of the specified folder ?
     *
     * A learner is any user with READ permission to a folder
     *
     * @param User   $user
     * @param Folder $folder
     * @return bool
     */
    public function isLearner(User $user, Folder $folder)
    {
        return $this->getFolderAccess($user, $folder->getId()) == UserAccess::ACCESS_READ;
    }


    /**
     * Returns the minimum access level across the collection of specified folders. Checks perms from:
     * * CMS
     * * GROUP
     * * ROLE
     *
     * @param User  $user
     * @param int[] $folderIds
     * @param bool  $cache
     * @return int
     */
    public function getMinFolderAccess(User $user, array $folderIds, $cache = true)
    {
        if (0 === count($folderIds)) {
            // Technically could throw an InvalidArgException, but this simplifies callers' code.
            return UserAccess::ACCESS_DENY;
        }

        // We must iterate all, rather than from index 1+ (ie fencepost alg.) to robustly account for different indexing
        $minAccess = null;
        foreach ($folderIds as $folderId) {
            $accessLevel = $this->getFolderAccess($user, $folderId, $cache);
            $minAccess   = null === $minAccess
                ? $accessLevel
                : min($minAccess, $accessLevel);
        }

        return $minAccess;
    }

    /**
     * Return the specified user's access level for the given folder, respects permissions:
     * * CMS
     * * GROUP
     * * ROLE
     *
     * @param User $user
     * @param int  $folderId
     * @param bool $cache Whether to use cached results or get them from DB again
     * @return int
     */
    public function getFolderAccess(User $user, $folderId, $cache = true)
    {
        // Superusers always have full access
        if ($user->isSuperuser()) {
            return UserAccess::ACCESS_FULL;
        }

        /** @var int[] $accessLevels Derived from different permission systems */
        $accessLevels = [];

        /* check cache before we proceed for shortcuts */
        if($cache && isset($this->cachedFolderAccess[$user->getId()][$folderId])){
            return $this->cachedFolderAccess[$user->getId()][$folderId];
        }

        // Check CMS service access level
        $userAccessMapper = UserAccessMapper::getInstance();
        try {
            $groupAccessLevel = $userAccessMapper->getAccess($user, null, UserAccess::SERVICE_CMS)->getAccessLevel();
            $accessLevels[] = $groupAccessLevel;

            // Short-circuit if full access
            if (UserAccess::ACCESS_FULL === $groupAccessLevel) {
                $this->cachedFolderAccess[$user->getId()][$folderId] = UserAccess::ACCESS_FULL;
                return UserAccess::ACCESS_FULL;
            }
        } catch (Mapper\Exception $e) {
            $accessLevels[] = UserAccess::ACCESS_DENY;
        }

        // Check Group access level
        try {
            $groupAccessLevel = $userAccessMapper->getAccess($user, $folderId, UserAccess::SERVICE_HOMEPAGE)->getAccessLevel();
            $accessLevels[] = $groupAccessLevel;

            // Short-circuit if full access
            if (UserAccess::ACCESS_FULL === $groupAccessLevel) {
                $this->cachedFolderAccess[$user->getId()][$folderId] = UserAccess::ACCESS_FULL;
                return UserAccess::ACCESS_FULL;
            }
        } catch (Mapper\Exception $e) {
            $accessLevels[] = UserAccess::ACCESS_DENY;
        }


        // Check role access level
        $roleAccessMapper = RoleAccessMapper::getInstance();
        try {
            $roleAccessLevel = $roleAccessMapper->getAccess($user, $folderId, $cache)->getType();
            $accessLevels[] = $roleAccessLevel;
        } catch (Mapper\Exception $e) {
            $accessLevels[] = UserAccess::ACCESS_DENY;
        }

        $maxLevel = max($accessLevels);
        return $this->cachedFolderAccess[$user->getId()][$folderId] = $maxLevel;
    }
}
