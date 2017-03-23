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
        return MSSQL::getInstance()->isResponsive();
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return '';
    }
}
