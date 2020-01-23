<?php

/**
 * Implementation of Edumate Class connector
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class External_Edumate_Ping extends External_Edumate_Connection implements External_PingInterface
{
    /**
     * @inheritdoc
     */
    public function isResponsive(): bool
    {
        try {
            $this->getConnection();
            return true;
        } catch (Exception $e) {
        }

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
