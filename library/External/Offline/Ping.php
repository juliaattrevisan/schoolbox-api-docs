<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Offline_Ping implements External_PingInterface
{
    /**
     * @inheritdoc
     */
    public function isResponsive()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getVersion()
    {
        return '';
    }
}