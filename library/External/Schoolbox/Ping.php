<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Database\Factory as DatabaseFactory;

/** @noinspection PhpUndefinedClassInspection */
class External_Schoolbox_Ping implements External_PingInterface
{
    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public function isResponsive()
    {
        try {
            /** @noinspection PhpDeprecationInspection */
            DatabaseFactory::getInstanceInternal();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getVersion()
    {
        return '';
    }
}
