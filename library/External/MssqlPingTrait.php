<?php
/**
 * A trait for MSSQL databases to return whether or not they are alive and accessible.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

trait External_MssqlPingTrait
{
    /**
     * Connects to MSSQL database, and returns whether the database is responsive.
     *
     * @return bool
     */
    public function isResponsive()
    {
        try {
            return MSSQL::getInstance()->isResponsive();
        } catch (\Alaress\Database\Exception $e) {
            // Something failed in connection - I know not what
            return false;
        }
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return '';
    }
}
