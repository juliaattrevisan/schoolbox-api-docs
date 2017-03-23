<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Schoolbox_Ping implements External_PingInterface
{
    /**
     * Connects to Schoolbox database in the same manner that the other classes
     * would, and returns whether the database is responsive.
     *
     * @return bool
     */
    public function isResponsive()
    {
        return Database::getInstance()->isConnected();
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return '';
    }
}