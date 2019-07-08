<?php

/**
 * An interface for external databases to return whether or not they are alive and accessible.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      https://schoolbox.com.au/
 */
interface External_PingInterface extends External\IConnector
{
    /**
     * @return bool
     */
    public function isResponsive(): bool;

    /**
     * Returns external database schema version
     * @return string
     */
    public function getVersion(): string;
}
