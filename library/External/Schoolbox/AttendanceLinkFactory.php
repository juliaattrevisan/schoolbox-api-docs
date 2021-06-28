<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\External\Importer\Endpoint\Factory;

/**
 * Class AttendanceLinkFactory
 *
 * Spawn one of the suds AttendanceLink generators, if applicable
 * Defaults to Offline mode otherwise
 */
class External_Schoolbox_AttendanceLinkFactory
{

    /**
     * Spawn one of the AttendanceLink instances suited to the current suds importer
     *
     * @param string|null $dataSource
     * @return External_AttendanceLinkInterface
     */
    public function produce(?string $dataSource): External_AttendanceLinkInterface
    {
        if (Factory::TASS_API === $dataSource) {
            return new \Schoolbox\External\Connector\TassApi\Base\Endpoint\AttendanceLink();
        }

        /* Default to Offline mode
         */
        return new External_Offline_AttendanceLink();
    }
}