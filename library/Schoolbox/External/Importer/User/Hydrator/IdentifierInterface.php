<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\User as UserModel;

/**
 * Specialized hydrator interface for the importer.
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
interface HydratorInterface
{
    /**
     * Updates $user based on fields in $row.
     *
     * @param  array     $row  The row from the CSV file
     * @param  UserModel $user The user object to update
     * @return UserModel An updated user object
     */
    public function hydrate($row, UserModel $user);
}
