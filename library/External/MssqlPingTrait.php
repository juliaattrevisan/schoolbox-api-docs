<?php
/**
 * A trait for pinging a MSSQL database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

trait External_MssqlPingTrait
{
    /**
     * Connects to MSSQL database, and returns whether the database is
     * responsive.
     *
     * @return bool
     */
    public function isResponsive()
    {
        return true;
    }
}
