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
     * @param \Schoolbox\User $user
     * @param array $folderIds
     * @return int
     */
    public function getFolderAccess(User $user, array $folderIds)
    {
        // Superusers always have full access

        if ($user->isSuperuser()) {
            return UserAccess::ACCESS_FULL;
        }

        $accessLevels = [];

        // Check CMS access level

        try
        {
            $accessLevels[] = UserAccessMapper::getInstance()
                ->getAccess($user, null, UserAccess::SERVICE_CMS)
                ->getAccessLevel();

            // Short-circuit if access is full

            if (in_array(UserAccess::ACCESS_FULL, $accessLevels)) {
                return UserAccess::ACCESS_FULL;
            }
        }
        catch (Mapper\Exception $e)
        {
            // noop
        }

        if (count($folderIds) != 1)
        {
            $accessLevels[] = UserAccessMapper::getInstance()
                ->userHasMinimumAccessToAll($user->getId(), $folderIds, UserAccess::ACCESS_WRITE)
                ? UserAccess::ACCESS_WRITE
                : UserAccess::ACCESS_DENY;
        }
        else
        {
            // Check user access level

            try
            {
                $accessLevels[] = UserAccessMapper::getInstance()
                    ->getAccess($user, $folderIds[0])
                    ->getAccessLevel();

                // Short-circuit if access is full

                if (in_array(UserAccess::ACCESS_FULL, $accessLevels)) {
                    return UserAccess::ACCESS_FULL;
                }
            }
            catch (Mapper\Exception $e)
            {
                // Noop
            }

            // Check role access level

            try
            {
                $accessLevels[] = RoleAccessMapper::getInstance()
                    ->getAccess($user, $folderIds[0])
                    ->getType();
            }
            catch (Mapper\Exception $e)
            {
                // Noop
            }
        }

        if (empty($accessLevels)) {
            return UserAccess::ACCESS_DENY;
        }

        return max($accessLevels);
    }
}
