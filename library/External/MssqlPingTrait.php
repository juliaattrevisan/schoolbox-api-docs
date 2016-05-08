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
        if (function_exists('mssql_connect')) {
            return mssql_connect(
                Cfg::get('external_db_host'),
                Cfg::get('external_db_user'),
                Cfg::get('external_db_passwd')
            ) !== false;
        }
        return false;
    }
}
