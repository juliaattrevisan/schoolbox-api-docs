<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\UserRelation\Hydrator;

use Schoolbox\External\UserRelation as UserRelationModel;
use Schoolbox\User as UserModel;

/**
 * Specialized hydrator interface for the import of user relations.
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
interface HydratorInterface
{
    /**
     * Updates relations based on fields in $row.
     *
     * @param  array       $row       The row from the CSV file
     * @param  UserModel[] $relations A list of users this user is related to.
     * @return UserModel[] An updated list
     */
    public function hydrate($row, $relations);
}
