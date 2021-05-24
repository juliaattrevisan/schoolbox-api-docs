<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Identifier;

use Schoolbox\User;

/**
 * Interface for user identifier classes.
 *
 * @package Schoolbox\External\Importer\User\Identifier
 */
interface IdentifierInterface
{
    /**
     * Does this identifier instance accept this row, and attempt to retrieve
     * a user object from it?
     *
     * @param  array $row
     * @return bool
     */
    public function accept($row);

    /**
     * Retrieves a user from this row.
     *
     * Should only be run if `accept()` returns true.
     *
     * @param  array $row
     * @return User
     * @throws \Exception if no user was retrievable
     */
    public function retrieve($row);
}
