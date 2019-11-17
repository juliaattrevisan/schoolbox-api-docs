<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Permission\Access;

use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Responsible for the application of various permission (aka access level) business rules and concepts.
 *
 * Business rules should be implemented here, rather than in the models and mappers to ensure:
 * 1. Cohesive responsibility sets
 * 2. Consistent architecture
 * 3. Testability
 */
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

        // Intentionally ordered after the empty array check. Not even SU's have an access level to the void.
        if ($user->isSuperuser()) {
            return UserAccess::ACCESS_FULL;
        }

        // We must iterate all, rather than from index 1+ (ie fencepost alg.) to robustly account for different indexing
        $minAccess = null;
        foreach ($folderIds as $folderId) {
            $accessLevel = $this->getFolderAccess($user, $folderId, $cache);

            // Short-circuit if any level is DENY. Cannot get less than this.
            if(UserAccess::ACCESS_DENY === $accessLevel) {
                return UserAccess::ACCESS_DENY;
            }

            // First iteration ?
            $minAccess = null === $minAccess ? $accessLevel : min($minAccess, $accessLevel);
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

        /* Note there is no folderId specified for the CMS service. The effect of this is that if the user
         * has FULL CMS access, we will cache FULL access for whatever folderId is specified.
         * We don't need to cache the value locally, we can rely on the Query Cache to do it for us.
         */
        $groupAccessLevel = $userAccessMapper->getAccessLevel($user, null, UserAccess::SERVICE_CMS);
        $accessLevels[] = $groupAccessLevel;

        // Short-circuit if full access
        if (UserAccess::ACCESS_FULL === $groupAccessLevel) {
            $this->cachedFolderAccess[$user->getId()][$folderId] = UserAccess::ACCESS_FULL;
            return UserAccess::ACCESS_FULL;
        }

        // Check Group access level
        $groupAccessLevel = $userAccessMapper->getAccessLevel($user, $folderId, UserAccess::SERVICE_HOMEPAGE);
        $accessLevels[] = $groupAccessLevel;

        // Short-circuit if full access
        if (UserAccess::ACCESS_FULL === $groupAccessLevel) {
            $this->cachedFolderAccess[$user->getId()][$folderId] = UserAccess::ACCESS_FULL;
            return UserAccess::ACCESS_FULL;
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
