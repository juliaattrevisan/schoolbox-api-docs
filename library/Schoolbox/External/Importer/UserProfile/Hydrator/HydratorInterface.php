<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer\UserProfile\Hydrator;

use Schoolbox\External\UserProfile as UserProfileModel;

/**
 * Specialized hydrator interface for the import of user profiles.
 *
 * @package Schoolbox\External\Importer\UserProfile\Hydrator
 */
interface HydratorInterface
{
    /**
     * Updates $user based on fields in $row.
     *
     * @param  array            $row  The row from the CSV file
     * @param  UserProfileModel $user The user profile object to update
     * @throws \UnexpectedValueException
     * @return UserProfileModel An updated user profile object
     */
    public function hydrate($row, UserProfileModel $user);
}
