<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class External_Offline_Ping implements External_PingInterface
{
    /**
     * @inheritdoc
     */
    public function isResponsive(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getVersion(): string
    {
        return '';
    }
}
