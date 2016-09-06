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
     * @var array
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
     * @param User $user
     * @param array $folderIds
     * @param bool $cache Whether to use cached results or get them from DB again
     * @return int
     */
    public function getFolderAccess(User $user, array $folderIds, $cache = true)
    {


        // Superusers always have full access

        if ($user->isSuperuser()) {
            return UserAccess::ACCESS_FULL;
        }

        $accessLevels = [];

        /* check cache before we proceed for shortcuts */
        if($cache && count($folderIds) == 1 && isset($this->cachedFolderAccess[$user->getId()][$folderIds[0]])){
            return $this->cachedFolderAccess[$user->getId()][$folderIds[0]];
        }

        // Check CMS access level

        try
        {
            if(!isset($this->cachedFolderAccess[$user->getId()][0]) ) {
                $accessLevels[] = UserAccessMapper::getInstance()
                    ->getAccess($user, null, UserAccess::SERVICE_CMS)
                    ->getAccessLevel();

                // Short-circuit if access is full
                if (in_array(UserAccess::ACCESS_FULL, $accessLevels)) {
                    $this->cachedFolderAccess[$user->getId()][0] = UserAccess::ACCESS_FULL;
                    return UserAccess::ACCESS_FULL;
                }
            }
        }
        catch (Mapper\Exception $e)
        {
            $this->cachedFolderAccess[$user->getId()][0] = UserAccess::ACCESS_DENY;
        }

        if (count($folderIds) != 1) {
            $accessLevels[] = UserAccessMapper::getInstance()
                ->userHasMinimumAccessToAll($user->getId(), $folderIds, UserAccess::ACCESS_WRITE)
                ? UserAccess::ACCESS_WRITE
                : UserAccess::ACCESS_DENY;
        } else {
            // Check user access level
            try
            {
                $accessLevels[] = UserAccessMapper::getInstance()
                    ->getAccess($user, $folderIds[0], UserAccess::SERVICE_HOMEPAGE, $cache)
                    ->getAccessLevel();

                // Short-circuit if access is full

                if (in_array(UserAccess::ACCESS_FULL, $accessLevels)) {
                        $this->cachedFolderAccess[$user->getId()][$folderIds[0]] = UserAccess::ACCESS_FULL;
                        return UserAccess::ACCESS_FULL;
                }
            } catch (Mapper\Exception $e) {
                // Noop
            }

            // Check role access level

            try {
                $accessLevels[] = RoleAccessMapper::getInstance()
                    ->getAccess($user, $folderIds[0], $cache)
                    ->getType();
            } catch (Mapper\Exception $e) {
                // Noop
            }
        }

        if (empty($accessLevels)) {
            $this->cachedFolderAccess[$user->getId()][$folderIds[0]] = UserAccess::ACCESS_DENY;
            return UserAccess::ACCESS_DENY;
        }

        $maxLevel = max($accessLevels);
        return $this->cachedFolderAccess[$user->getId()][$folderIds[0]] = $maxLevel;

    }
}
