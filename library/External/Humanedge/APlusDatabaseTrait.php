<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Factory as DatabaseFactory;

/**
 * Trait for A+ database usage implementation of user retrieve methods.
 */
trait External_Humanedge_APlusDatabaseTrait
{
    /**
     * A database connection for the A+ database (if required).
     *
     * @var IConnector
     */
    private $aPlusDatabase;

    /**
     * Lazy-load A+ database connection
     *
     * @return IConnector
     */
    private function loadAPlusDatabase()
    {
        if (!($this->aPlusDatabase instanceof IConnector)) {
            // Finagle a config out of special snowflake A+ config
            $dbf  = new DatabaseFactory();
            $conn = $dbf->createMssql();
            $conn->connect(
                Config::get('external_humanedge_aplus_db_host'),
                Config::get('external_humanedge_aplus_db_db'),
                Config::get('external_humanedge_aplus_db_user'),
                Config::get('external_humanedge_aplus_db_passwd'),
                Config::get('external_humanedge_aplus_db_host')
            );
            $this->aPlusDatabase = $conn;
        }
        return $this->aPlusDatabase;
    }
}
