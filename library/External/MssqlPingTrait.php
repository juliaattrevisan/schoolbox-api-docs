<?php

use Alaress\Database\Exception as DatabaseException;
use Schoolbox\Database\Factory;

/**
 * A trait for MSSQL databases to return whether or not they are alive and accessible.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

trait External_MssqlPingTrait
{
    /**
     * Connects to MSSQL database, and returns whether the database is responsive.
     *
     * @return bool
     */
    public function isResponsive(): bool
    {
        try {
            Factory::getInstanceExternal();
            return true;
        } catch (DatabaseException $e) {
            // Something failed in connection - I know not what
            return false;
        }
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return '';
    }
}
