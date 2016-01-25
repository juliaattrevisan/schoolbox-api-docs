<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * Trait for A+ database usage implementation of user retrieve methods.
 */
trait External_Humanedge_APlusDatabaseTrait
{
    /**
     * A database connection for the A+ database (if required).
     *
     * @var MSSQL
     */
    private $aPlusDatabase;

    /**
     * Lazy-load A+ database connection
     *
     * @return MSSQL
     */
    private function loadAPlusDatabase()
    {
        if (!($this->aPlusDatabase instanceof MSSQL)) {
            $this->aPlusDatabase = new MSSQL(
                Config::get('external_humanedge_aplus_db_db'),
                [
                    'host'     => Config::get('external_humanedge_aplus_db_host'),
                    'user'     => Config::get('external_humanedge_aplus_db_user'),
                    'passwd'   => Config::get('external_humanedge_aplus_db_passwd'),
                    'db'       => Config::get('external_humanedge_aplus_db_db'),
                    'encoding' => Config::get('external_db_encoding'),
                ],
                true
            );
        }
        return $this->aPlusDatabase;
    }
}
