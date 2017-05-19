<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Denbigh_Ping implements External_PingInterface
{
    /**
     * Connects to Denbigh database in the same manner that the other classes
     * would, and returns whether the database is responsive.
     *
     * @return bool
     */
    public function isResponsive()
    {
        $database = new Database();
        $hostName = Config::get('external_db_host');
        $databaseName = Config::get('external_db_db');
        $username = Config::get('external_db_user');
        $password = Config::get('external_db_passwd');

        try {
            return $database->connect(
                "mysql:host=$hostName;dbname=$databaseName", $username, $password,
                ['throw' => true]
            );
        } catch (Exception $ex) {
        }

        return false;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return '';
    }
}