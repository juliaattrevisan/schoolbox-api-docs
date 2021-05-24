<?php

/**
 * @author    Anyone Foolish Enough To Claim It <everyone@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Database\Factory as DatabaseFactory;

/** @noinspection PhpUndefinedClassInspection */
class External_Denbigh_Ping implements External_PingInterface
{
    /**
     * {@inheritDoc}
     * @return bool
     */
    public function isResponsive(): bool
    {
        try {
            /** @noinspection PhpDeprecationInspection */
            DatabaseFactory::getInstanceInternal();

            /** @noinspection PhpDeprecationInspection */
            DatabaseFactory::getInstanceExternal();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     * @return string
     */
    public function getVersion(): string
    {
        return '';
    }
}
