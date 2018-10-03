<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;

/**
 * Specification of a user that is restricted to only audience to groups/classes where they have READ+ access
 *
 * A user is restricted if they are/have none of:
 * * a superuser
 * * a user with WRITE+ News Service perm
 * * READ+ CMS perm
 */
class ValidRestrictedSearch
{

    /**
     * Determine whether $user is one of the restricted types of users
     *
     * To use a Folder as an audiencing target,
     *    - Folders must have a class code or be a Group
     *    - Folder can't be deleted
     *
     * Anything else is rolled over or otherwise inaccessible and shouldn't appear as a search result
     * The result set is affected by whether $user has READ+ access to the folder in some way
     *
     *    - Superusers/Comms Officers/CMS Perms aren't limited to what they can search and can audience
     *    - Restrict non-superusers/non-comms-officer/non-CMS Perms people
     *
     * @param User $user
     * @return bool
     * @throws \Schoolbox\Mapper\Exception
     */
    public function isSatisfiedBy(User $user): bool
    {
        return !$user->isSuperuser() && !$this->hasCMSPermission($user) && !$this->hasNewsServiceWritePerm($user);
    }


    /**
     * Check whether $user is a CMS Service Permission holder
     *
     * @param User $user
     * @return bool
     */
    private function hasCMSPermission(User $user): bool
    {
        $accessList = $user->getAccessList();

        if (!empty($accessList)) {
            foreach ($accessList as $access) {
                $hasCMSPermission = $access->getService() === UserAccess::SERVICE_CMS;
                $isReadAccess     = $access->getAccessLevel() >= UserAccess::ACCESS_READ;

                if ($hasCMSPermission && $isReadAccess) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * Returns whether the specified user has WRITE+ NewsService permissions
     *
     * @param User $user
     * @return bool
     * @throws \Schoolbox\Mapper\Exception
     */
    private function hasNewsServiceWritePerm(User $user): bool
    {
        return UserAccessMapper::getInstance()->getAccess($user, null, UserAccess::SERVICE_NEWS) >= UserAccess::ACCESS_WRITE;
    }
}
